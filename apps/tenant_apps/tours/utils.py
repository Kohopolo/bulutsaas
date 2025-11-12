"""
Tur Modülü Yardımcı Fonksiyonlar
PDF, Voucher, Harita işlemleri
"""
from django.conf import settings
from django.http import HttpResponse
from django.template.loader import render_to_string
from decimal import Decimal
import os
from datetime import datetime


def generate_tour_pdf_program(tour):
    """
    Tur PDF programı oluştur
    reportlab veya weasyprint kullanılabilir
    """
    try:
        from reportlab.lib.pagesizes import A4
        from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
        from reportlab.lib.units import cm
        from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak
        from reportlab.lib import colors
        from reportlab.pdfbase import pdfmetrics
        from reportlab.pdfbase.ttfonts import TTFont
        from io import BytesIO
        
        # PDF buffer
        buffer = BytesIO()
        
        # PDF dokümanı oluştur
        doc = SimpleDocTemplate(
            buffer,
            pagesize=A4,
            rightMargin=2*cm,
            leftMargin=2*cm,
            topMargin=2*cm,
            bottomMargin=2*cm
        )
        
        # Stil tanımlamaları
        styles = getSampleStyleSheet()
        title_style = ParagraphStyle(
            'CustomTitle',
            parent=styles['Heading1'],
            fontSize=24,
            textColor=colors.HexColor('#2d3e50'),
            spaceAfter=30,
            alignment=1,  # Center
        )
        
        # İçerik oluştur
        story = []
        
        # Başlık
        story.append(Paragraph(tour.name, title_style))
        story.append(Spacer(1, 0.5*cm))
        
        # Tur Bilgileri
        info_data = [
            ['Tur Kodu:', tour.code],
            ['Bölge:', tour.region.name if tour.region else '-'],
            ['Lokasyon:', tour.location.name if tour.location else '-'],
            ['Süre:', tour.get_duration_display()],
            ['Ulaşım:', tour.get_transport_type_display()],
        ]
        
        info_table = Table(info_data, colWidths=[5*cm, 10*cm])
        info_table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (0, -1), colors.HexColor('#f5f7fa')),
            ('TEXTCOLOR', (0, 0), (-1, -1), colors.black),
            ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
            ('FONTNAME', (0, 0), (0, -1), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, -1), 10),
            ('BOTTOMPADDING', (0, 0), (-1, -1), 12),
            ('TOPPADDING', (0, 0), (-1, -1), 12),
            ('GRID', (0, 0), (-1, -1), 1, colors.grey),
        ]))
        story.append(info_table)
        story.append(Spacer(1, 1*cm))
        
        # Açıklama
        if tour.description:
            story.append(Paragraph('<b>Tur Açıklaması:</b>', styles['Heading2']))
            story.append(Paragraph(tour.description.replace('\n', '<br/>'), styles['Normal']))
            story.append(Spacer(1, 0.5*cm))
        
        # Program
        if tour.programs.exists():
            story.append(Paragraph('<b>Gün Gün Program:</b>', styles['Heading2']))
            story.append(Spacer(1, 0.3*cm))
            
            for program in tour.programs.all().order_by('day_number', 'sort_order'):
                day_title = f"{program.day_number}. Gün"
                if program.title:
                    day_title += f": {program.title}"
                
                story.append(Paragraph(day_title, styles['Heading3']))
                
                if program.description:
                    story.append(Paragraph(program.description.replace('\n', '<br/>'), styles['Normal']))
                
                if program.activities:
                    story.append(Paragraph(f"<b>Aktiviteler:</b> {program.activities}", styles['Normal']))
                
                if program.meals:
                    story.append(Paragraph(f"<b>Yemekler:</b> {program.meals}", styles['Normal']))
                
                if program.accommodation:
                    story.append(Paragraph(f"<b>Konaklama:</b> {program.accommodation}", styles['Normal']))
                
                story.append(Spacer(1, 0.5*cm))
        
        # Fiyat Bilgileri
        story.append(PageBreak())
        story.append(Paragraph('<b>Fiyat Bilgileri:</b>', styles['Heading2']))
        story.append(Spacer(1, 0.3*cm))
        
        price_data = [
            ['Yetişkin Fiyatı:', f"{tour.adult_price} {tour.currency}"],
            ['Çocuk Fiyatı:', f"{tour.child_price} {tour.currency}"],
        ]
        
        if tour.group_price:
            price_data.append(['Grup Fiyatı (10+ Kişi):', f"{tour.group_price} {tour.currency}"])
        
        price_table = Table(price_data, colWidths=[5*cm, 10*cm])
        price_table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (0, -1), colors.HexColor('#f5f7fa')),
            ('TEXTCOLOR', (0, 0), (-1, -1), colors.black),
            ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
            ('FONTNAME', (0, 0), (0, -1), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, -1), 10),
            ('BOTTOMPADDING', (0, 0), (-1, -1), 12),
            ('TOPPADDING', (0, 0), (-1, -1), 12),
            ('GRID', (0, 0), (-1, -1), 1, colors.grey),
        ]))
        story.append(price_table)
        story.append(Spacer(1, 0.5*cm))
        
        # Fiyata Dahil/Dahil Olmayanlar
        if tour.price_includes:
            story.append(Paragraph('<b>Fiyata Dahil:</b>', styles['Heading3']))
            for item in tour.price_includes.split('\n'):
                if item.strip():
                    story.append(Paragraph(f"• {item.strip()}", styles['Normal']))
            story.append(Spacer(1, 0.3*cm))
        
        if tour.price_excludes:
            story.append(Paragraph('<b>Fiyata Dahil Olmayanlar:</b>', styles['Heading3']))
            for item in tour.price_excludes.split('\n'):
                if item.strip():
                    story.append(Paragraph(f"• {item.strip()}", styles['Normal']))
            story.append(Spacer(1, 0.3*cm))
        
        # Notlar
        if tour.notes:
            story.append(Paragraph('<b>Önemli Notlar:</b>', styles['Heading3']))
            story.append(Paragraph(tour.notes.replace('\n', '<br/>'), styles['Normal']))
        
        # PDF oluştur
        doc.build(story)
        
        # PDF'i dosyaya kaydet
        pdf_dir = os.path.join(settings.MEDIA_ROOT, 'tours', 'pdfs')
        os.makedirs(pdf_dir, exist_ok=True)
        
        pdf_filename = f"tour_{tour.code}_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
        pdf_path = os.path.join(pdf_dir, pdf_filename)
        
        with open(pdf_path, 'wb') as f:
            f.write(buffer.getvalue())
        
        # Tour modeline PDF path'i kaydet (eğer field varsa)
        # tour.pdf_program = f"tours/pdfs/{pdf_filename}"
        # tour.save()
        
        buffer.seek(0)
        return buffer, pdf_filename
        
    except ImportError:
        # reportlab yüklü değilse basit HTML PDF oluştur
        return None, None


def generate_reservation_voucher(reservation):
    """
    Rezervasyon voucher'ı oluştur (HTML)
    """
    context = {
        'reservation': reservation,
        'tour': reservation.tour,
        'tour_date': reservation.tour_date,
    }
    
    # Voucher şablonu varsa onu kullan
    if reservation.tour.voucher_template:
        template = reservation.tour.voucher_template.template_html
        # Template'i render et (basit string replacement)
        voucher_html = template.replace('{{reservation_code}}', reservation.reservation_code)
        voucher_html = voucher_html.replace('{{customer_name}}', f"{reservation.customer_name} {reservation.customer_surname}")
        voucher_html = voucher_html.replace('{{tour_name}}', reservation.tour.name)
        voucher_html = voucher_html.replace('{{tour_date}}', reservation.tour_date.date.strftime('%d.%m.%Y'))
        voucher_html = voucher_html.replace('{{total_amount}}', str(reservation.total_amount))
        voucher_html = voucher_html.replace('{{currency}}', reservation.currency)
        return voucher_html
    else:
        # Varsayılan voucher şablonu
        return render_to_string('tenant/tours/reservations/voucher_default.html', context)


def create_whatsapp_link(phone, message):
    """
    WhatsApp.me link oluştur
    """
    # Telefon numarasını temizle (sadece rakamlar)
    phone_clean = ''.join(filter(str.isdigit, phone))
    
    # Mesajı URL encode et
    from urllib.parse import quote
    message_encoded = quote(message)
    
    return f"https://wa.me/{phone_clean}?text={message_encoded}"

