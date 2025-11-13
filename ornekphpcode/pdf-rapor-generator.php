<?php
require_once __DIR__ . '/../includes/pdf-generator.php';
require_once __DIR__ . '/../config/database.php';

// PDF Generator sınıfını başlat
$pdfGenerator = new PDFGenerator($pdo);

// PDF içeriği
$content = '
<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="text-align: center; border-bottom: 3px solid #2c3e50; padding-bottom: 20px; margin-bottom: 30px;">
        <h1 style="color: #2c3e50; font-size: 24px; margin: 0;">🏨 Otel Yönetim Sistemi</h1>
        <h2 style="color: #34495e; font-size: 18px; margin: 10px 0;">Profesyonel Geliştirme Raporu</h2>
        <p style="color: #7f8c8d; font-size: 14px; margin: 5px 0;">Analiz Tarihi: ' . date('d.m.Y H:i') . '</p>
    </div>

    <div style="margin-bottom: 25px;">
        <h2 style="color: #34495e; font-size: 18px; border-bottom: 2px solid #3498db; padding-bottom: 5px;">📊 Mevcut Durum Analizi</h2>
        
        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div style="flex: 1; background-color: #d5f4e6; padding: 15px; border-radius: 5px; border-left: 4px solid #27ae60;">
                <h3 style="color: #27ae60; margin-top: 0;">✅ Güçlü Yanlar</h3>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Temel rezervasyon sistemi</li>
                    <li>Oda yönetimi</li>
                    <li>Fiyatlandırma sistemi (özel, sezonluk, kampanya)</li>
                    <li>Kanal entegrasyonları (Booking, Expedia, vb.)</li>
                    <li>Ödeme modülü</li>
                    <li>PDF raporlama</li>
                    <li>Çoklu dil desteği</li>
                    <li>Tema sistemi</li>
                </ul>
            </div>
            <div style="flex: 1; background-color: #fadbd8; padding: 15px; border-radius: 5px; border-left: 4px solid #e74c3c;">
                <h3 style="color: #e74c3c; margin-top: 0;">⚠️ Eksik Alanlar</h3>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Müşteri deneyimi odaklı özellikler</li>
                    <li>Operasyonel verimlilik araçları</li>
                    <li>Gelişmiş raporlama ve analitik</li>
                    <li>Mobil optimizasyon</li>
                    <li>Güvenlik ve yedekleme</li>
                    <li>Entegrasyon ve API\'ler</li>
                </ul>
            </div>
        </div>
    </div>

    <div style="margin-bottom: 25px;">
        <h2 style="color: #34495e; font-size: 18px; border-bottom: 2px solid #3498db; padding-bottom: 5px;">🚀 Profesyonel Geliştirme Önerileri</h2>
        
        <h3 style="color: #2c3e50; font-size: 16px; margin-top: 20px;">1. Müşteri Deneyimi ve Frontend</h3>
        <ul style="padding-left: 20px;">
            <li>Modern Web Arayüzü: React/Vue.js ile SPA frontend</li>
            <li>Mobil Uygulama: React Native/Flutter ile native app</li>
            <li>Online Check-in/Check-out: QR kod ile self-service</li>
            <li>Müşteri Portalı: Rezervasyon geçmişi, tercihler, puanlama</li>
            <li>Canlı Chat Desteği: Müşteri hizmetleri entegrasyonu</li>
            <li>Sosyal Medya Entegrasyonu: Instagram, Facebook rezervasyon</li>
            <li>Çoklu Dil ve Para Birimi: Dinamik dil/para değişimi</li>
            <li>Erişilebilirlik: WCAG 2.1 uyumlu tasarım</li>
        </ul>

        <h3 style="color: #2c3e50; font-size: 16px; margin-top: 20px;">2. Operasyonel Yönetim</h3>
        <ul style="padding-left: 20px;">
            <li>Housekeeping Modülü: Oda temizlik takibi, görev yönetimi</li>
            <li>Maintenance Sistemi: Arıza bildirimi, bakım planlama</li>
            <li>Stok Yönetimi: Minibar, temizlik malzemeleri, yatak takımları</li>
            <li>Personel Yönetimi: Vardiya planlama, performans takibi</li>
            <li>Güvenlik Sistemi: Kamera entegrasyonu, giriş-çıkış logları</li>
            <li>Enerji Yönetimi: Akıllı termostat, enerji tüketim takibi</li>
            <li>Oda Durumu Otomasyonu: IoT sensörlerle otomatik güncelleme</li>
        </ul>

        <h3 style="color: #2c3e50; font-size: 16px; margin-top: 20px;">3. Gelişmiş Rezervasyon Sistemi</h3>
        <ul style="padding-left: 20px;">
            <li>Dynamic Pricing: AI destekli fiyat optimizasyonu</li>
            <li>Revenue Management: Gelir maksimizasyon algoritmaları</li>
            <li>Group Booking: Toplu rezervasyon yönetimi</li>
            <li>Package Deals: Paket tur, aktivite entegrasyonu</li>
            <li>Loyalty Program: Sadakat puanı, üyelik sistemi</li>
            <li>Upselling Engine: Otomatik ek hizmet önerileri</li>
            <li>Waitlist Management: Bekleme listesi sistemi</li>
            <li>Cancellation Management: İptal politikaları, ücret hesaplama</li>
        </ul>

        <h3 style="color: #2c3e50; font-size: 16px; margin-top: 20px;">4. Analitik ve Raporlama</h3>
        <ul style="padding-left: 20px;">
            <li>Business Intelligence Dashboard: Gerçek zamanlı KPI\'lar</li>
            <li>Predictive Analytics: Talep tahmini, fiyat önerileri</li>
            <li>Customer Analytics: Müşteri segmentasyonu, davranış analizi</li>
            <li>Financial Reporting: Detaylı mali raporlar, vergi entegrasyonu</li>
            <li>Competitor Analysis: Rakip fiyat takibi</li>
            <li>Weather Integration: Hava durumu bazlı fiyatlandırma</li>
            <li>Event Calendar: Etkinlik takvimi, özel günler</li>
            <li>Custom Reports Builder: Kullanıcı tanımlı rapor oluşturucu</li>
        </ul>

        <h3 style="color: #2c3e50; font-size: 16px; margin-top: 20px;">5. Entegrasyon ve API\'ler</h3>
        <ul style="padding-left: 20px;">
            <li>PMS Entegrasyonları: Opera, Fidelio, Amadeus</li>
            <li>Channel Manager: Tüm kanalları tek yerden yönetim</li>
            <li>Payment Gateway: Stripe, PayPal, yerel ödeme sistemleri</li>
            <li>CRM Entegrasyonu: Salesforce, HubSpot</li>
            <li>Email Marketing: Mailchimp, SendGrid entegrasyonu</li>
            <li>SMS Gateway: Twilio, yerel SMS sağlayıcıları</li>
            <li>Social Media APIs: Instagram, Facebook, Twitter</li>
            <li>Weather API: Hava durumu verileri</li>
            <li>Maps Integration: Google Maps, konum servisleri</li>
        </ul>

        <h3 style="color: #2c3e50; font-size: 16px; margin-top: 20px;">6. Güvenlik ve Yedekleme</h3>
        <ul style="padding-left: 20px;">
            <li>Two-Factor Authentication: 2FA güvenlik</li>
            <li>Role-Based Access Control: Detaylı yetki sistemi</li>
            <li>Audit Trail: Tüm işlemlerin loglanması</li>
            <li>Data Encryption: Veri şifreleme</li>
            <li>Automated Backups: Otomatik yedekleme sistemi</li>
            <li>Disaster Recovery: Felaket kurtarma planı</li>
            <li>GDPR Compliance: Veri koruma uyumluluğu</li>
            <li>PCI DSS Compliance: Ödeme kartı güvenliği</li>
        </ul>
    </div>

    <div style="margin-bottom: 25px;">
        <h2 style="color: #34495e; font-size: 18px; border-bottom: 2px solid #3498db; padding-bottom: 5px;">📈 Öncelik Sıralaması</h2>
        
        <h3 style="color: #e74c3c; font-size: 16px; margin-top: 20px;">Yüksek Öncelik (3-6 ay)</h3>
        <ul style="padding-left: 20px;">
            <li>Modern web arayüzü</li>
            <li>Mobil uygulama</li>
            <li>Gelişmiş raporlama</li>
            <li>Güvenlik güncellemeleri</li>
            <li>API entegrasyonları</li>
        </ul>

        <h3 style="color: #f39c12; font-size: 16px; margin-top: 20px;">Orta Öncelik (6-12 ay)</h3>
        <ul style="padding-left: 20px;">
            <li>Housekeeping modülü</li>
            <li>Loyalty program</li>
            <li>Dynamic pricing</li>
            <li>CRM entegrasyonu</li>
            <li>Email marketing</li>
        </ul>

        <h3 style="color: #27ae60; font-size: 16px; margin-top: 20px;">Düşük Öncelik (12+ ay)</h3>
        <ul style="padding-left: 20px;">
            <li>AI/ML özellikleri</li>
            <li>IoT entegrasyonu</li>
            <li>AR/VR özellikleri</li>
            <li>Blockchain</li>
            <li>Advanced analytics</li>
        </ul>
    </div>

    <div style="margin-bottom: 25px;">
        <h2 style="color: #34495e; font-size: 18px; border-bottom: 2px solid #3498db; padding-bottom: 5px;">💰 Maliyet Tahmini</h2>
        <div style="background-color: #ecf0f1; padding: 15px; border-radius: 5px; border: 1px solid #bdc3c7;">
            <div style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dotted #95a5a6;">
                <span>Temel Geliştirmeler:</span>
                <span>50.000 - 100.000₺</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dotted #95a5a6;">
                <span>Orta Seviye Özellikler:</span>
                <span>100.000 - 200.000₺</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dotted #95a5a6;">
                <span>Gelişmiş Özellikler:</span>
                <span>200.000 - 500.000₺</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span>Enterprise Çözümler:</span>
                <span>500.000₺+</span>
            </div>
        </div>
    </div>

    <div style="margin-bottom: 25px;">
        <h2 style="color: #34495e; font-size: 18px; border-bottom: 2px solid #3498db; padding-bottom: 5px;">🎯 Sonuç ve Öneriler</h2>
        <p>Bu analiz, projenizi profesyonel bir otel yönetim sistemi haline getirmek için gerekli adımları göstermektedir. Öncelikle yüksek öncelikli özelliklerden başlayarak, aşamalı olarak sisteminizi geliştirmeniz önerilir.</p>
        
        <p><strong>İlk Adımlar:</strong></p>
        <ul style="padding-left: 20px;">
            <li>Modern web arayüzü geliştirme</li>
            <li>Mobil uygulama planlama</li>
            <li>Güvenlik güncellemeleri</li>
            <li>API entegrasyonları</li>
        </ul>
    </div>

    <div style="text-align: center; font-size: 10px; color: #7f8c8d; border-top: 1px solid #bdc3c7; padding-top: 10px; margin-top: 30px;">
        <p>Otel Yönetim Sistemi Geliştirme Raporu | ' . date('d.m.Y') . '</p>
    </div>
</div>';

// PDF oluştur
$pdf_content = $pdfGenerator->createPDF($content, 'A4');

// PDF'i indir
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="otel-sistemi-gelistirme-raporu-' . date('Y-m-d') . '.pdf"');
header('Content-Length: ' . strlen($pdf_content));
echo $pdf_content;
?>
