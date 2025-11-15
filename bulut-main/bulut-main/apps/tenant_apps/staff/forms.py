"""
Personel Yönetimi Modülü Forms
"""
from django import forms
from .models import Staff, Shift, LeaveRequest, PerformanceReview, SalaryRecord, StaffSettings


class StaffForm(forms.ModelForm):
    """Personel Formu"""
    class Meta:
        model = Staff
        fields = ['user', 'employee_id', 'first_name', 'last_name', 'department', 'position', 'email', 'phone', 'address', 'hire_date', 'termination_date', 'employment_type', 'salary', 'currency', 'is_active', 'notes']
        widgets = {
            'user': forms.Select(attrs={'class': 'form-control'}),
            'employee_id': forms.TextInput(attrs={'class': 'form-control'}),
            'first_name': forms.TextInput(attrs={'class': 'form-control'}),
            'last_name': forms.TextInput(attrs={'class': 'form-control'}),
            'department': forms.Select(attrs={'class': 'form-control'}),
            'position': forms.TextInput(attrs={'class': 'form-control'}),
            'email': forms.EmailInput(attrs={'class': 'form-control'}),
            'phone': forms.TextInput(attrs={'class': 'form-control'}),
            'address': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'hire_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'termination_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'employment_type': forms.Select(attrs={'class': 'form-control'}),
            'salary': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'is_active': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }


class ShiftForm(forms.ModelForm):
    """Vardiya Formu"""
    class Meta:
        model = Shift
        fields = ['staff', 'shift_date', 'shift_type', 'start_time', 'end_time', 'status', 'notes']
        widgets = {
            'staff': forms.Select(attrs={'class': 'form-control'}),
            'shift_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'shift_type': forms.Select(attrs={'class': 'form-control'}),
            'start_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'end_time': forms.TimeInput(attrs={'class': 'form-control', 'type': 'time'}),
            'status': forms.Select(attrs={'class': 'form-control'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        if hotel:
            self.fields['staff'].queryset = Staff.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('last_name', 'first_name')


class LeaveRequestForm(forms.ModelForm):
    """İzin Talebi Formu"""
    class Meta:
        model = LeaveRequest
        fields = ['staff', 'leave_type', 'start_date', 'end_date', 'total_days', 'reason']
        widgets = {
            'staff': forms.Select(attrs={'class': 'form-control'}),
            'leave_type': forms.Select(attrs={'class': 'form-control'}),
            'start_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'end_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'total_days': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'reason': forms.Textarea(attrs={'class': 'form-control', 'rows': 4}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        if hotel:
            self.fields['staff'].queryset = Staff.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('last_name', 'first_name')


class PerformanceReviewForm(forms.ModelForm):
    """Performans Değerlendirmesi Formu"""
    class Meta:
        model = PerformanceReview
        fields = ['staff', 'review_period_start', 'review_period_end', 'attendance_score', 'performance_score', 'teamwork_score', 'communication_score', 'overall_score', 'strengths', 'areas_for_improvement', 'goals', 'notes']
        widgets = {
            'staff': forms.Select(attrs={'class': 'form-control'}),
            'review_period_start': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'review_period_end': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'attendance_score': forms.NumberInput(attrs={'class': 'form-control', 'min': 1, 'max': 10}),
            'performance_score': forms.NumberInput(attrs={'class': 'form-control', 'min': 1, 'max': 10}),
            'teamwork_score': forms.NumberInput(attrs={'class': 'form-control', 'min': 1, 'max': 10}),
            'communication_score': forms.NumberInput(attrs={'class': 'form-control', 'min': 1, 'max': 10}),
            'overall_score': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0, 'max': 10}),
            'strengths': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'areas_for_improvement': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'goals': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        if hotel:
            self.fields['staff'].queryset = Staff.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('last_name', 'first_name')


class SalaryRecordForm(forms.ModelForm):
    """Maaş Kaydı Formu"""
    class Meta:
        model = SalaryRecord
        fields = ['staff', 'salary_month', 'base_salary', 'overtime_hours', 'overtime_rate', 'bonuses', 'deductions', 'gross_salary', 'net_salary', 'currency', 'paid', 'paid_date', 'notes']
        widgets = {
            'staff': forms.Select(attrs={'class': 'form-control'}),
            'salary_month': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'base_salary': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'overtime_hours': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'overtime_rate': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'bonuses': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'deductions': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'gross_salary': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'net_salary': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'currency': forms.Select(attrs={'class': 'form-control'}),
            'paid': forms.CheckboxInput(attrs={'class': 'form-check-input'}),
            'paid_date': forms.DateInput(attrs={'class': 'form-control', 'type': 'date'}),
            'notes': forms.Textarea(attrs={'class': 'form-control', 'rows': 3}),
        }
    
    def __init__(self, *args, **kwargs):
        hotel = kwargs.pop('hotel', None)
        super().__init__(*args, **kwargs)
        if hotel:
            self.fields['staff'].queryset = Staff.objects.filter(hotel=hotel, is_active=True, is_deleted=False).order_by('last_name', 'first_name')


class StaffSettingsForm(forms.ModelForm):
    """Personel Yönetimi Ayarları Formu"""
    class Meta:
        model = StaffSettings
        fields = ['default_shift_duration', 'overtime_threshold', 'default_overtime_rate', 'annual_leave_days', 'sick_leave_days']
        widgets = {
            'default_shift_duration': forms.NumberInput(attrs={'class': 'form-control', 'min': 1}),
            'overtime_threshold': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'default_overtime_rate': forms.NumberInput(attrs={'class': 'form-control', 'step': '0.01', 'min': 0}),
            'annual_leave_days': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
            'sick_leave_days': forms.NumberInput(attrs={'class': 'form-control', 'min': 0}),
        }

