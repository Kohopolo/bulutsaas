from django.contrib import admin
from .models import Subscription, Payment
from .forms import SubscriptionForm, PaymentForm, PaymentInlineForm


class PaymentInline(admin.TabularInline):
    form = PaymentInlineForm
    model = Payment
    extra = 1
    readonly_fields = ['created_at']
    fields = ['amount', 'currency', 'status', 'payment_method', 'payment_date']
    verbose_name = 'Ödeme'
    verbose_name_plural = 'Ödemeler'


@admin.register(Subscription)
class SubscriptionAdmin(admin.ModelAdmin):
    form = SubscriptionForm
    list_display = ['tenant', 'package', 'period', 'start_date', 'end_date', 'days_remaining', 'status', 'auto_renew']
    list_filter = ['status', 'period', 'auto_renew', 'created_at']
    search_fields = ['tenant__name', 'package__name']
    readonly_fields = ['created_at', 'updated_at', 'days_remaining']
    inlines = [PaymentInline]
    
    fieldsets = (
        ('Abonelik Bilgileri', {
            'fields': ('tenant', 'package', 'period')
        }),
        ('Tarihler', {
            'fields': ('start_date', 'end_date', 'next_billing_date', 'days_remaining')
        }),
        ('Fiyat', {
            'fields': ('amount', 'currency')
        }),
        ('Durum', {
            'fields': ('status', 'auto_renew')
        }),
        ('Stripe Bilgileri', {
            'fields': ('stripe_subscription_id', 'stripe_customer_id'),
            'classes': ('collapse',)
        }),
        ('Notlar', {
            'fields': ('notes',),
            'classes': ('collapse',)
        }),
    )


@admin.register(Payment)
class PaymentAdmin(admin.ModelAdmin):
    form = PaymentForm
    list_display = ['subscription', 'amount', 'currency', 'status', 'payment_method', 'payment_date']
    list_filter = ['status', 'payment_method', 'created_at']
    search_fields = ['subscription__tenant__name', 'invoice_number']
    readonly_fields = ['created_at', 'updated_at']
    
    fieldsets = (
        ('Abonelik', {
            'fields': ('subscription',)
        }),
        ('Ödeme Bilgileri', {
            'fields': ('amount', 'currency', 'payment_method', 'status', 'payment_date')
        }),
        ('Fatura', {
            'fields': ('invoice_number', 'invoice_url')
        }),
        ('Stripe Bilgileri', {
            'fields': ('stripe_payment_intent_id', 'stripe_charge_id'),
            'classes': ('collapse',)
        }),
        ('Notlar', {
            'fields': ('notes',),
            'classes': ('collapse',)
        }),
    )



