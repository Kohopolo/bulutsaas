"""
Personel Yönetimi Admin
"""
from django.contrib import admin
from .models import Staff, Shift, LeaveRequest, PerformanceReview, SalaryRecord, StaffSettings


@admin.register(Staff)
class StaffAdmin(admin.ModelAdmin):
    list_display = ['full_name', 'employee_id', 'department', 'position', 'is_active']
    list_filter = ['department', 'employment_type', 'is_active']


@admin.register(Shift)
class ShiftAdmin(admin.ModelAdmin):
    list_display = ['staff', 'shift_date', 'shift_type', 'start_time', 'end_time', 'status']
    list_filter = ['status', 'shift_type', 'shift_date']


@admin.register(LeaveRequest)
class LeaveRequestAdmin(admin.ModelAdmin):
    list_display = ['staff', 'leave_type', 'start_date', 'end_date', 'total_days', 'status']
    list_filter = ['status', 'leave_type']


@admin.register(PerformanceReview)
class PerformanceReviewAdmin(admin.ModelAdmin):
    list_display = ['staff', 'review_period_start', 'review_period_end', 'overall_score']
    list_filter = ['review_period_start']


@admin.register(SalaryRecord)
class SalaryRecordAdmin(admin.ModelAdmin):
    list_display = ['staff', 'salary_month', 'net_salary', 'paid', 'paid_date']
    list_filter = ['paid', 'salary_month']


@admin.register(StaffSettings)
class StaffSettingsAdmin(admin.ModelAdmin):
    list_display = ['hotel', 'default_shift_duration', 'annual_leave_days']

