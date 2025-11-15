"""
Tur Yönetim Admin
"""
from django.contrib import admin
from django.utils.html import format_html
from .models import (
    TourRegion, TourLocation, TourCity, TourType, TourVoucherTemplate,
    Tour, TourDate, TourProgram, TourImage, TourVideo, TourExtraService, TourRoute,
    TourReservation, TourGuest, TourReservationExtraService, TourPayment, TourReview,
    TourWaitingList, TourCustomer, TourLoyaltyHistory, TourCustomerNote,
    TourAgency, TourReservationCommission,
    TourGuide, TourVehicle, TourHotel, TourTransfer, TourReservationOperation,
    TourNotificationTemplate, TourNotification,
    TourCampaign, TourPromoCode
)


# ==================== DİNAMİK YÖNETİM ADMİN ====================

@admin.register(TourRegion)
class TourRegionAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'is_active', 'sort_order']
    list_filter = ['is_active']
    search_fields = ['name', 'code']
    prepopulated_fields = {'code': ('name',)}


@admin.register(TourLocation)
class TourLocationAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'location_type', 'is_active', 'sort_order']
    list_filter = ['location_type', 'is_active']
    search_fields = ['name', 'code']
    prepopulated_fields = {'code': ('name',)}


@admin.register(TourCity)
class TourCityAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'country', 'is_active', 'sort_order']
    list_filter = ['country', 'is_active']
    search_fields = ['name', 'code', 'country']
    prepopulated_fields = {'code': ('name',)}


@admin.register(TourType)
class TourTypeAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'is_active', 'sort_order']
    list_filter = ['is_active']
    search_fields = ['name', 'code']
    prepopulated_fields = {'code': ('name',)}


@admin.register(TourVoucherTemplate)
class TourVoucherTemplateAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'is_default', 'is_active']
    list_filter = ['is_default', 'is_active']
    search_fields = ['name', 'code']
    prepopulated_fields = {'code': ('name',)}


# ==================== TUR ADMİN ====================

class TourDateInline(admin.TabularInline):
    model = TourDate
    extra = 1
    fields = ['date', 'adult_price', 'child_price', 'max_adults', 'max_children', 'is_active', 'is_full']


class TourProgramInline(admin.TabularInline):
    model = TourProgram
    extra = 1
    fields = ['day_number', 'title', 'description', 'meals', 'accommodation', 'sort_order']


class TourImageInline(admin.TabularInline):
    model = TourImage
    extra = 1
    fields = ['image', 'title', 'alt_text', 'is_active', 'sort_order']


class TourVideoInline(admin.TabularInline):
    model = TourVideo
    extra = 1
    fields = ['video_type', 'video_url', 'title', 'is_active', 'sort_order']


class TourExtraServiceInline(admin.TabularInline):
    model = TourExtraService
    extra = 1
    fields = ['name', 'description', 'price_per_person', 'is_active', 'sort_order']


class TourRouteInline(admin.TabularInline):
    model = TourRoute
    extra = 1
    fields = ['city', 'order', 'is_departure', 'is_destination', 'stay_duration']


@admin.register(Tour)
class TourAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'region', 'location', 'tour_type', 'status', 'is_active', 'is_featured', 'view_count', 'reservation_count']
    list_filter = ['status', 'is_active', 'is_featured', 'region', 'location', 'tour_type', 'transport_type']
    search_fields = ['name', 'code', 'description']
    prepopulated_fields = {'code': ('name',), 'slug': ('name',)}
    readonly_fields = ['view_count', 'reservation_count', 'rating_average', 'rating_count', 'created_at', 'updated_at']
    
    fieldsets = (
        ('Temel Bilgiler', {
            'fields': ('name', 'code', 'slug', 'description', 'status', 'is_active', 'is_featured', 'sort_order')
        }),
        ('Kategoriler', {
            'fields': ('region', 'location', 'city', 'tour_type')
        }),
        ('Ulaşım ve Süre', {
            'fields': ('transport_type', 'duration_days', 'duration_nights', 'departure_time', 'return_time')
        }),
        ('Tur Detayları', {
            'fields': ('cities_to_visit', 'notes', 'hotels', 'price_includes', 'price_excludes')
        }),
        ('Kontenjan', {
            'fields': ('max_adults', 'max_children', 'child_age_min', 'child_age_max')
        }),
        ('Fiyatlandırma', {
            'fields': ('adult_price', 'child_price', 'group_price', 'group_min_people')
        }),
        ('Kampanya', {
            'fields': ('campaign_price', 'campaign_start_date', 'campaign_end_date')
        }),
        ('Dinamik Fiyatlandırma', {
            'fields': ('enable_dynamic_pricing', 'enable_early_booking', 'enable_last_minute',
                      'enable_weekend_pricing', 'enable_demand_pricing', 'enable_holiday_pricing'),
            'classes': ('collapse',)
        }),
        ('Sezon Fiyatlandırma', {
            'fields': ('high_season_start_month', 'high_season_end_month', 'high_season_price_increase',
                      'low_season_start_month', 'low_season_end_month', 'low_season_price_decrease'),
            'classes': ('collapse',)
        }),
        ('Erken Rezervasyon', {
            'fields': ('early_booking_90_days_discount', 'early_booking_60_days_discount', 'early_booking_30_days_discount'),
            'classes': ('collapse',)
        }),
        ('Son Dakika', {
            'fields': ('last_minute_7_days_discount', 'last_minute_3_days_discount',
                      'last_minute_auto_activate', 'last_minute_capacity_threshold'),
            'classes': ('collapse',)
        }),
        ('Hafta Sonu ve Talep', {
            'fields': ('weekend_price_increase', 'demand_capacity_threshold', 'demand_price_increase',
                      'holiday_price_increase'),
            'classes': ('collapse',)
        }),
        ('Medya', {
            'fields': ('main_image',)
        }),
        ('SEO', {
            'fields': ('meta_title', 'meta_description', 'meta_keywords'),
            'classes': ('collapse',)
        }),
        ('İstatistikler', {
            'fields': ('view_count', 'reservation_count', 'rating_average', 'rating_count'),
            'classes': ('collapse',)
        }),
        ('Ayarlar', {
            'fields': ('settings',),
            'classes': ('collapse',)
        }),
    )
    
    inlines = [
        TourDateInline,
        TourProgramInline,
        TourImageInline,
        TourVideoInline,
        TourExtraServiceInline,
        TourRouteInline,
    ]


# ==================== REZERVASYON ADMİN ====================

class TourGuestInline(admin.TabularInline):
    model = TourGuest
    extra = 1
    fields = ['first_name', 'last_name', 'is_adult', 'age', 'tc_no', 'passport_no']


class TourReservationExtraServiceInline(admin.TabularInline):
    model = TourReservationExtraService
    extra = 1
    fields = ['extra_service', 'quantity', 'unit_price', 'total_price']


class TourPaymentInline(admin.TabularInline):
    model = TourPayment
    extra = 1
    fields = ['amount', 'currency', 'payment_method', 'status', 'payment_date', 'transaction_id']


@admin.register(TourReservation)
class TourReservationAdmin(admin.ModelAdmin):
    list_display = ['reservation_code', 'tour', 'tour_date', 'customer_name', 'customer_surname', 'total_people', 'total_amount', 'status', 'payment_status', 'created_at']
    list_filter = ['status', 'payment_status', 'tour', 'created_at']
    search_fields = ['reservation_code', 'customer_name', 'customer_surname', 'customer_email', 'customer_phone']
    readonly_fields = ['reservation_code', 'created_at', 'updated_at']
    
    fieldsets = (
        ('Rezervasyon Bilgileri', {
            'fields': ('reservation_code', 'tour', 'tour_date', 'status', 'sales_person')
        }),
        ('Müşteri Bilgileri', {
            'fields': ('customer', 'customer_name', 'customer_surname', 'customer_email', 'customer_phone', 'customer_tc', 'customer_address')
        }),
        ('Acente ve Promosyon', {
            'fields': ('agency', 'promo_code', 'campaign')
        }),
        ('Kişi Bilgileri', {
            'fields': ('adult_count', 'child_count', 'total_people')
        }),
        ('Fiyat Hesaplama', {
            'fields': ('adult_price', 'child_price', 'subtotal', 'extra_services_total', 'discount_amount', 'total_amount', 'currency')
        }),
        ('Ödeme', {
            'fields': ('payment_status',)
        }),
        ('Voucher', {
            'fields': ('voucher_generated', 'voucher_pdf')
        }),
        ('Notlar', {
            'fields': ('notes',)
        }),
    )
    
    inlines = [
        TourGuestInline,
        TourReservationExtraServiceInline,
        TourPaymentInline,
    ]


@admin.register(TourPayment)
class TourPaymentAdmin(admin.ModelAdmin):
    list_display = ['reservation', 'amount', 'currency', 'payment_method', 'status', 'payment_date', 'created_at']
    list_filter = ['status', 'payment_method', 'created_at']
    search_fields = ['reservation__reservation_code', 'transaction_id']
    readonly_fields = ['created_at', 'updated_at']


@admin.register(TourReview)
class TourReviewAdmin(admin.ModelAdmin):
    list_display = ['tour', 'customer_name', 'rating', 'is_approved', 'is_active', 'created_at']
    list_filter = ['rating', 'is_approved', 'is_active', 'created_at']
    search_fields = ['tour__name', 'customer_name', 'comment']
    readonly_fields = ['created_at', 'updated_at']


# ==================== BEKLEME LİSTESİ ADMİN ====================

@admin.register(TourWaitingList)
class TourWaitingListAdmin(admin.ModelAdmin):
    list_display = ['tour', 'tour_date', 'customer_name', 'customer_surname', 'total_people', 'status', 'priority', 'created_at']
    list_filter = ['status', 'tour', 'created_at']
    search_fields = ['customer_name', 'customer_surname', 'customer_email', 'customer_phone']
    readonly_fields = ['created_at', 'updated_at']


# ==================== CRM ADMİN ====================

@admin.register(TourCustomer)
class TourCustomerAdmin(admin.ModelAdmin):
    list_display = ['customer_code', 'first_name', 'last_name', 'email', 'phone', 'vip_level', 'total_reservations', 'total_spent', 'loyalty_points', 'is_active']
    list_filter = ['vip_level', 'is_active', 'is_vip', 'created_at']
    search_fields = ['customer_code', 'first_name', 'last_name', 'email', 'phone']
    readonly_fields = ['customer_code', 'total_reservations', 'total_spent', 'last_reservation_date', 'created_at', 'updated_at']
    filter_horizontal = ['preferred_regions', 'preferred_tour_types']


@admin.register(TourLoyaltyHistory)
class TourLoyaltyHistoryAdmin(admin.ModelAdmin):
    list_display = ['customer', 'points', 'reason', 'reservation', 'created_at']
    list_filter = ['created_at']
    search_fields = ['customer__customer_code', 'customer__email', 'reason']
    readonly_fields = ['created_at', 'updated_at']


@admin.register(TourCustomerNote)
class TourCustomerNoteAdmin(admin.ModelAdmin):
    list_display = ['customer', 'note', 'is_important', 'created_by', 'created_at']
    list_filter = ['is_important', 'created_at']
    search_fields = ['customer__customer_code', 'customer__email', 'note']
    readonly_fields = ['created_at', 'updated_at']


# ==================== KOMİSYON VE ACENTE ADMİN ====================

@admin.register(TourAgency)
class TourAgencyAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'contact_person', 'email', 'phone', 'commission_type', 'commission_rate', 'is_active']
    list_filter = ['is_active', 'commission_type']
    search_fields = ['name', 'code', 'contact_person', 'email']
    prepopulated_fields = {'code': ('name',)}


@admin.register(TourReservationCommission)
class TourReservationCommissionAdmin(admin.ModelAdmin):
    list_display = ['reservation', 'agency', 'base_amount', 'commission_rate', 'commission_amount', 'payment_status', 'payment_date']
    list_filter = ['payment_status', 'agency', 'created_at']
    search_fields = ['reservation__reservation_code', 'agency__name']
    readonly_fields = ['created_at', 'updated_at']


# ==================== OPERASYONEL YÖNETİM ADMİN ====================

@admin.register(TourGuide)
class TourGuideAdmin(admin.ModelAdmin):
    list_display = ['name', 'surname', 'phone', 'license_number', 'hourly_rate', 'daily_rate', 'is_active']
    list_filter = ['is_active']
    search_fields = ['name', 'surname', 'phone', 'license_number']


@admin.register(TourVehicle)
class TourVehicleAdmin(admin.ModelAdmin):
    list_display = ['plate_number', 'brand', 'model', 'year', 'capacity', 'vehicle_type', 'driver_name', 'daily_rate', 'is_active']
    list_filter = ['vehicle_type', 'is_active']
    search_fields = ['plate_number', 'brand', 'model', 'driver_name']


@admin.register(TourHotel)
class TourHotelAdmin(admin.ModelAdmin):
    list_display = ['name', 'city', 'star_rating', 'daily_rate_per_person', 'is_active']
    list_filter = ['star_rating', 'is_active', 'city']
    search_fields = ['name', 'city__name', 'address']


@admin.register(TourTransfer)
class TourTransferAdmin(admin.ModelAdmin):
    list_display = ['name', 'transfer_type', 'from_location', 'to_location', 'price_per_person', 'price_per_vehicle', 'is_active']
    list_filter = ['transfer_type', 'is_active']
    search_fields = ['name', 'from_location', 'to_location']


@admin.register(TourReservationOperation)
class TourReservationOperationAdmin(admin.ModelAdmin):
    list_display = ['reservation', 'guide', 'vehicle', 'hotel_nights', 'total_operation_cost']
    search_fields = ['reservation__reservation_code']
    filter_horizontal = ['hotels', 'transfers']
    readonly_fields = ['total_operation_cost', 'created_at', 'updated_at']


# ==================== BİLDİRİM SİSTEMİ ADMİN ====================

@admin.register(TourNotificationTemplate)
class TourNotificationTemplateAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'notification_type', 'trigger_event', 'is_active']
    list_filter = ['notification_type', 'trigger_event', 'is_active']
    search_fields = ['name', 'code']
    prepopulated_fields = {'code': ('name',)}


@admin.register(TourNotification)
class TourNotificationAdmin(admin.ModelAdmin):
    list_display = ['template', 'notification_type', 'recipient_email', 'recipient_phone', 'status', 'sent_at', 'created_at']
    list_filter = ['status', 'notification_type', 'created_at']
    search_fields = ['recipient_email', 'recipient_phone', 'subject']
    readonly_fields = ['created_at', 'updated_at', 'sent_at']


# ==================== KAMPANYA VE PROMOSYON ADMİN ====================

@admin.register(TourCampaign)
class TourCampaignAdmin(admin.ModelAdmin):
    list_display = ['name', 'code', 'campaign_type', 'start_date', 'end_date', 'usage_count', 'usage_limit', 'is_active', 'is_featured']
    list_filter = ['campaign_type', 'is_active', 'is_featured', 'start_date', 'end_date']
    search_fields = ['name', 'code', 'description']
    prepopulated_fields = {'code': ('name',)}
    filter_horizontal = ['applicable_tours', 'applicable_tour_types']
    readonly_fields = ['usage_count', 'created_at', 'updated_at']


@admin.register(TourPromoCode)
class TourPromoCodeAdmin(admin.ModelAdmin):
    list_display = ['code', 'campaign', 'start_date', 'end_date', 'usage_count', 'usage_limit', 'is_active']
    list_filter = ['is_active', 'start_date', 'end_date', 'campaign']
    search_fields = ['code', 'campaign__name']
    readonly_fields = ['usage_count', 'created_at', 'updated_at']

