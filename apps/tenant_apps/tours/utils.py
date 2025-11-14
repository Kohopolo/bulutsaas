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
    reportlab veya weasyprint kullanılabilir (Türkçe karakter desteği ile)
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
        import os
        
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
        
        # Türkçe karakter desteği için font kaydı
        turkish_font_name = 'Helvetica'
        try:
            font_paths = [
                'C:/Windows/Fonts/dejavu/DejaVuSans.ttf',
                'C:/Windows/Fonts/arial.ttf',
                'C:/Windows/Fonts/tahoma.ttf',
            ]
            
            for font_path in font_paths:
                if os.path.exists(font_path):
                    try:
                        pdfmetrics.registerFont(TTFont('TurkishFont', font_path))
                        turkish_font_name = 'TurkishFont'
                        break
                    except Exception:
                        continue
        except Exception:
            pass
        
        # Stil tanımlamaları (Türkçe font ile)
        styles = getSampleStyleSheet()
        title_style = ParagraphStyle(
            'CustomTitle',
            parent=styles['Heading1'],
            fontName=turkish_font_name,
            fontSize=24,
            textColor=colors.HexColor('#2d3e50'),
            spaceAfter=30,
            alignment=1,  # Center
            encoding='utf-8',
        )
        
        # Normal stil için Türkçe font
        normal_style = ParagraphStyle(
            'TurkishNormal',
            parent=styles['Normal'],
            fontName=turkish_font_name,
            encoding='utf-8',
        )
        
        heading2_style = ParagraphStyle(
            'TurkishHeading2',
            parent=styles['Heading2'],
            fontName=turkish_font_name,
            encoding='utf-8',
        )
        
        heading3_style = ParagraphStyle(
            'TurkishHeading3',
            parent=styles['Heading3'],
            fontName=turkish_font_name,
            encoding='utf-8',
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
            story.append(Paragraph('<b>Tur Açıklaması:</b>', heading2_style))
            story.append(Paragraph(tour.description.replace('\n', '<br/>'), normal_style))
            story.append(Spacer(1, 0.5*cm))
        
        # Program
        if tour.programs.exists():
            story.append(Paragraph('<b>Gün Gün Program:</b>', heading2_style))
            story.append(Spacer(1, 0.3*cm))
            
            for program in tour.programs.all().order_by('day_number', 'sort_order'):
                day_title = f"{program.day_number}. Gün"
                if program.title:
                    day_title += f": {program.title}"
                
                story.append(Paragraph(day_title, heading3_style))
                
                if program.description:
                    story.append(Paragraph(program.description.replace('\n', '<br/>'), normal_style))
                
                if program.activities:
                    story.append(Paragraph(f"<b>Aktiviteler:</b> {program.activities}", normal_style))
                
                if program.meals:
                    story.append(Paragraph(f"<b>Yemekler:</b> {program.meals}", normal_style))
                
                if program.accommodation:
                    story.append(Paragraph(f"<b>Konaklama:</b> {program.accommodation}", normal_style))
                
                story.append(Spacer(1, 0.5*cm))
        
        # Fiyat Bilgileri
        story.append(PageBreak())
        story.append(Paragraph('<b>Fiyat Bilgileri:</b>', heading2_style))
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
            story.append(Paragraph('<b>Fiyata Dahil:</b>', heading3_style))
            for item in tour.price_includes.split('\n'):
                if item.strip():
                    story.append(Paragraph(f"• {item.strip()}", normal_style))
            story.append(Spacer(1, 0.3*cm))
        
        if tour.price_excludes:
            story.append(Paragraph('<b>Fiyata Dahil Olmayanlar:</b>', heading3_style))
            for item in tour.price_excludes.split('\n'):
                if item.strip():
                    story.append(Paragraph(f"• {item.strip()}", normal_style))
            story.append(Spacer(1, 0.3*cm))
        
        # Notlar
        if tour.notes:
            story.append(Paragraph('<b>Önemli Notlar:</b>', heading3_style))
            story.append(Paragraph(tour.notes.replace('\n', '<br/>'), normal_style))
        
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
    Rezervasyon voucher'ı oluştur (HTML) - Türkçe karakter desteği ile
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
    else:
        # Varsayılan voucher şablonu
        voucher_html = render_to_string('tenant/tours/reservations/voucher_default.html', context)
    
    # UTF-8 meta tag'i ve DOCTYPE kontrolü
    if '<!DOCTYPE' not in voucher_html and '<!doctype' not in voucher_html:
        if '<html' not in voucher_html.lower():
            voucher_html = f'<!DOCTYPE html>\n<html lang="tr">\n{voucher_html}\n</html>'
        else:
            voucher_html = f'<!DOCTYPE html>\n{voucher_html}'
    
    if '<meta charset="UTF-8">' not in voucher_html and '<meta charset="utf-8">' not in voucher_html:
        if '<head>' in voucher_html:
            voucher_html = voucher_html.replace('<head>', '<head>\n    <meta charset="UTF-8">', 1)
        elif '<HEAD>' in voucher_html:
            voucher_html = voucher_html.replace('<HEAD>', '<HEAD>\n    <meta charset="UTF-8">', 1)
        elif '<html>' in voucher_html:
            voucher_html = voucher_html.replace('<html>', '<html>\n<head>\n    <meta charset="UTF-8">\n</head>', 1)
    
    # CSS'e charset ve font-family ekle
    if '<style>' in voucher_html:
        if '@charset' not in voucher_html:
            voucher_html = voucher_html.replace('<style>', '<style>\n@charset "UTF-8";', 1)
        if 'font-family' not in voucher_html.lower():
            voucher_html = voucher_html.replace('<style>', '<style>\nbody, * { font-family: Arial, "DejaVu Sans", "Liberation Sans", sans-serif; }', 1)
    else:
        # Style tag'i yoksa ekle
        if '<head>' in voucher_html:
            voucher_html = voucher_html.replace('<head>', '<head>\n    <style>\n@charset "UTF-8";\nbody, * { font-family: Arial, "DejaVu Sans", "Liberation Sans", sans-serif; }\n    </style>', 1)
    
    return voucher_html


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

