"""
PDF Oluşturma Utility Fonksiyonları
Güvenli ve güvenilir PDF oluşturma için ReportLab kullanılır
"""
import logging
from io import BytesIO
from django.http import HttpResponse
from django.contrib import messages
from django.shortcuts import redirect

logger = logging.getLogger(__name__)


def html_to_pdf_reportlab(html_content, filename='document.pdf'):
    """
    HTML içeriğini ReportLab kullanarak PDF'e dönüştür (Türkçe karakter desteği ile)
    
    Args:
        html_content: HTML string içeriği (UTF-8)
        filename: PDF dosya adı
    
    Returns:
        BytesIO: PDF içeriği veya None (hata durumunda)
    """
    try:
        from reportlab.lib.pagesizes import A4
        from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
        from reportlab.lib.units import cm
        from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak, Table, TableStyle
        from reportlab.lib import colors
        from reportlab.pdfbase import pdfmetrics
        from reportlab.pdfbase.ttfonts import TTFont
        from reportlab.lib.fonts import addMapping
        import re
        import os
        
        # HTML'i UTF-8 olarak garanti et
        if isinstance(html_content, bytes):
            html_content = html_content.decode('utf-8')
        
        # Türkçe karakter desteği için font kaydı
        # Windows sistem fontlarını kullan (Türkçe karakter desteği var)
        try:
            # DejaVu Sans fontunu dene (genellikle sistemde yüklü)
            font_paths = [
                'C:/Windows/Fonts/dejavu/DejaVuSans.ttf',
                'C:/Windows/Fonts/arial.ttf',
                'C:/Windows/Fonts/tahoma.ttf',
            ]
            
            turkish_font_name = 'DejaVuSans'
            font_registered = False
            
            for font_path in font_paths:
                if os.path.exists(font_path):
                    try:
                        pdfmetrics.registerFont(TTFont('TurkishFont', font_path))
                        turkish_font_name = 'TurkishFont'
                        font_registered = True
                        logger.info(f'Türkçe font kaydedildi: {font_path}')
                        break
                    except Exception as e:
                        logger.warning(f'Font kaydedilemedi {font_path}: {str(e)}')
                        continue
            
            if not font_registered:
                # Sistem fontlarını kullan (Türkçe karakter desteği var)
                turkish_font_name = 'Helvetica'
                logger.info('Sistem fontu kullanılıyor (Helvetica - Türkçe karakter desteği var)')
        except Exception as e:
            logger.warning(f'Font kaydı yapılamadı: {str(e)}, varsayılan font kullanılıyor')
            turkish_font_name = 'Helvetica'
        
        # HTML'i temizle ve basit tag'leri ReportLab formatına dönüştür
        html_content = html_content.replace('<br>', '<br/>')
        html_content = html_content.replace('<br />', '<br/>')
        
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
        
        # Stil tanımlamaları (Türkçe font ile)
        styles = getSampleStyleSheet()
        
        normal_style = ParagraphStyle(
            'TurkishNormal',
            parent=styles['Normal'],
            fontName=turkish_font_name,
            encoding='utf-8',
        )
        
        title_style = ParagraphStyle(
            'TurkishTitle',
            parent=styles['Heading1'],
            fontName=turkish_font_name,
            fontSize=18,
            textColor=colors.HexColor('#2d3e50'),
            spaceAfter=12,
            alignment=1,  # Center
            encoding='utf-8',
        )
        
        # İçerik oluştur
        story = []
        
        # HTML'i parçalara ayır ve işle
        # Basit bir HTML parser (sadece temel tag'ler için)
        parts = re.split(r'(<[^>]+>)', html_content)
        current_text = ''
        
        for part in parts:
            if part.startswith('<'):
                # Tag işleme
                if part.lower() in ['<br/>', '<br>', '<p>', '</p>']:
                    if current_text.strip():
                        # Türkçe karakterleri koru
                        story.append(Paragraph(current_text.strip(), normal_style))
                        current_text = ''
                    if part.lower() in ['<br/>', '<br>']:
                        story.append(Spacer(1, 0.3*cm))
                elif part.lower() == '<h1>':
                    if current_text.strip():
                        story.append(Paragraph(current_text.strip(), normal_style))
                        current_text = ''
                elif part.lower() == '</h1>':
                    if current_text.strip():
                        story.append(Paragraph(current_text.strip(), title_style))
                        story.append(Spacer(1, 0.5*cm))
                        current_text = ''
            else:
                current_text += part
        
        # Kalan metni ekle
        if current_text.strip():
            story.append(Paragraph(current_text.strip(), normal_style))
        
        # PDF oluştur
        doc.build(story)
        
        buffer.seek(0)
        logger.info(f'ReportLab ile PDF oluşturuldu (Türkçe karakter desteği ile): {filename}')
        return buffer
        
    except ImportError:
        logger.warning('ReportLab bulunamadı')
        return None
    except Exception as e:
        logger.error(f'ReportLab ile PDF oluşturulurken hata: {str(e)}', exc_info=True)
        return None


def generate_pdf_response(html_content, filename='document.pdf', fallback_to_html=False):
    """
    HTML içeriğinden PDF response oluştur
    
    Öncelik sırası:
    1. WeasyPrint (HTML/CSS desteği mükemmel, Türkçe karakter desteği var)
    2. ReportLab (güvenli ve güvenilir, Türkçe font desteği ile)
    3. xhtml2pdf (son çare, güvenlik riski olabilir)
    
    Args:
        html_content: HTML string içeriği (UTF-8 encoding)
        filename: PDF dosya adı
        fallback_to_html: PDF oluşturulamazsa HTML döndür
    
    Returns:
        HttpResponse: PDF response veya None
    """
    pdf_data = None
    
    # HTML içeriğini UTF-8 string olarak garanti et
    if isinstance(html_content, bytes):
        html_content = html_content.decode('utf-8')
    elif not isinstance(html_content, str):
        html_content = str(html_content)
    
    # UTF-8 encoding'i garanti et
    html_content = html_content.encode('utf-8').decode('utf-8')
    
    # 1. WeasyPrint dene (HTML/CSS desteği mükemmel, Türkçe karakter desteği var)
    try:
        from weasyprint import HTML
        from weasyprint.text.fonts import FontConfiguration
        
        # Font yapılandırması (Türkçe karakter desteği için)
        font_config = FontConfiguration()
        
        # HTML'i PDF'e dönüştür (string olarak, encoding belirtmeden)
        # WeasyPrint otomatik olarak UTF-8 kullanır
        pdf_data = HTML(
            string=html_content
        ).write_pdf(font_config=font_config)
        
        logger.info(f'WeasyPrint ile PDF oluşturuldu (Türkçe karakter desteği ile): {filename}')
    except (ImportError, OSError) as e:
        logger.warning(f'WeasyPrint kullanılamıyor ({type(e).__name__}): {str(e)}')
    except Exception as e:
        logger.error(f'WeasyPrint ile PDF oluşturulurken hata: {str(e)}', exc_info=True)
    
    # 2. ReportLab dene (Türkçe font desteği ile)
    if not pdf_data:
        try:
            buffer = html_to_pdf_reportlab(html_content, filename)
            if buffer:
                pdf_data = buffer.getvalue()
                buffer.close()
                logger.info(f'ReportLab ile PDF oluşturuldu (Türkçe font desteği ile): {filename}')
        except Exception as e:
            logger.warning(f'ReportLab ile PDF oluşturulamadı: {str(e)}')
    
    # 3. xhtml2pdf dene (son çare - güvenlik riski olabilir)
    if not pdf_data:
        try:
            from xhtml2pdf import pisa
            from io import BytesIO
            
            result = BytesIO()
            # UTF-8 encoding ile dene
            pdf = pisa.pisaDocument(
                BytesIO(html_content.encode('UTF-8')),
                result,
                encoding='UTF-8'
            )
            if pdf.err:
                raise Exception(f'xhtml2pdf hatası: {pdf.err}')
            pdf_data = result.getvalue()
            logger.warning(f'xhtml2pdf ile PDF oluşturuldu (güvenlik riski olabilir): {filename}')
        except ImportError:
            logger.warning('xhtml2pdf bulunamadı')
        except Exception as e:
            logger.error(f'xhtml2pdf ile PDF oluşturulurken hata: {str(e)}', exc_info=True)
    
    # PDF oluşturulduysa döndür
    if pdf_data:
        response = HttpResponse(pdf_data, content_type='application/pdf')
        response['Content-Disposition'] = f'attachment; filename="{filename}"'
        return response
    
    # PDF oluşturulamadıysa None döndür veya HTML döndür
    if fallback_to_html:
        return HttpResponse(html_content, content_type='text/html; charset=utf-8')
    
    return None

