/**
 * رسوخ العمرانية - منطق صفحة تسجيل الاهتمام
 * Register Interest Form Handler
 */

// إعدادات خدمة الإرسال
const CONFIG = {
  // مفتاح Web3Forms الخاص ببريد: sltanbnnasr346@gmail.com
  web3formsKey: '54d82128-0637-44f5-86b4-77830ea0b174',
  
  // اسم الشركة الافتراضي
  companyName: 'رسوخ العمرانية',

  // نقطة نهاية API
  web3formsEndpoint: 'https://api.web3forms.com/submit',
  phpEndpoint: 'send_mail.php'
};

document.addEventListener('DOMContentLoaded', () => {
  // عناصر النموذج
  const form = document.getElementById('customerForm');
  const submitBtn = document.getElementById('submitBtn');
  const btnText = submitBtn.querySelector('.btn-text');
  const successScreen = document.getElementById('successScreen');
  const newSubmissionBtn = document.getElementById('newSubmissionBtn');
  const generalAlert = document.getElementById('generalAlert');
  const alertMessage = document.getElementById('alertMessage');

  // الحقول
  const fullNameInput = document.getElementById('fullName');
  const phoneInput = document.getElementById('phoneNumber');
  const cityInput = document.getElementById('cityName');
  const paymentSelect = document.getElementById('paymentMethod');
  const honeypotInput = document.getElementById('website_url_check');

  // مجموعات الحقول ورسائل الخطأ
  const groupFullName = document.getElementById('groupFullName');
  const groupPhone = document.getElementById('groupPhone');
  const groupCity = document.getElementById('groupCity');
  const groupPayment = document.getElementById('groupPayment');

  const fullNameError = document.getElementById('fullNameError');
  const phoneError = document.getElementById('phoneError');
  const cityError = document.getElementById('cityError');
  const paymentError = document.getElementById('paymentError');

  // نص الزر الافتراضي
  const defaultBtnText = 'تسجيل اهتمامك';

  // حالة الإرسال لمنع النقر المتكرر
  let isSubmitting = false;

  /**
   * إظهار رسالة خطأ لحقل معين
   */
  function showError(groupElem, errorElem, message) {
    if (!groupElem || !errorElem) return;
    groupElem.classList.add('has-error');
    const textSpan = errorElem.querySelector('.error-text');
    if (textSpan) {
      textSpan.textContent = message;
    }
    errorElem.style.display = 'flex';
  }

  /**
   * مسح رسالة الخطأ لحقل معين
   */
  function clearError(groupElem, errorElem) {
    if (!groupElem || !errorElem) return;
    groupElem.classList.remove('has-error');
    errorElem.style.display = 'none';
  }

  /**
   * إخفاء التنبيه العام
   */
  function hideGeneralAlert() {
    generalAlert.classList.remove('is-visible');
    generalAlert.style.setProperty('display', 'none', 'important');
  }

  /**
   * إظهار تنبيه عام عند فشل الإرسال
   */
  function showGeneralAlert(message) {
    alertMessage.textContent = message || 'تعذر إرسال البيانات حاليًا، يرجى المحاولة مرة أخرى.';
    generalAlert.classList.add('is-visible');
    generalAlert.style.removeProperty('display');
    generalAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // التأكد من إخفاء التنبيه فور تحميل الصفحة
  hideGeneralAlert();

  /**
   * استخراج وتنسيق رقم الجوال السعودي فقط
   * يقبل: 05xxxxxxxx أو 5xxxxxxxx أو +9665xxxxxxxx أو 009665xxxxxxxx
   */
  function parseSaudiPhone(rawPhone) {
    if (!rawPhone) return null;
    let clean = rawPhone.replace(/[\s\-\(\)\+]/g, '');

    // إزالة البادئات الدولية
    if (clean.startsWith('00966')) {
      clean = clean.substring(5);
    } else if (clean.startsWith('966')) {
      clean = clean.substring(3);
    }

    // إزالة صفر البداية في حال إدخال 05xxxxxxxx
    if (clean.startsWith('05')) {
      clean = clean.substring(1);
    }

    // يجب أن يبدأ بـ 5 ويكون 9 أرقام (5xxxxxxxx)
    if (/^5[0-9]{8}$/.test(clean)) {
      return {
        isValid: true,
        digits: clean,
        localFormatted: '0' + clean,
        international: '+966' + clean,
        display: '+966 ' + clean.substring(0, 1) + clean.substring(1, 3) + ' ' + clean.substring(3, 6) + ' ' + clean.substring(6)
      };
    }

    return { isValid: false };
  }

  /**
   * التحقق من صحة الاسم الكامل
   */
  function validateFullName() {
    const val = fullNameInput.value.trim();
    if (!val) {
      showError(groupFullName, fullNameError, 'هذا الحقل مطلوب.');
      return false;
    }
    if (val.length < 3) {
      showError(groupFullName, fullNameError, 'يرجى إدخال اسم صحيح مكون من 3 أحرف على الأقل.');
      return false;
    }
    clearError(groupFullName, fullNameError);
    return true;
  }

  /**
   * التحقق من أن رقم الجوال سعودي فقط
   */
  function validatePhone() {
    const rawVal = phoneInput.value.trim();
    if (!rawVal) {
      showError(groupPhone, phoneError, 'هذا الحقل مطلوب.');
      return false;
    }

    const saudi = parseSaudiPhone(rawVal);
    if (saudi && saudi.isValid) {
      clearError(groupPhone, phoneError);
      return true;
    } else {
      showError(groupPhone, phoneError, 'يرجى إدخال رقم جوال سعودي صحيح يبدأ بـ 05 (مثال: 05xxxxxxxx).');
      return false;
    }
  }

  /**
   * التحقق من صحة المدينة
   */
  function validateCity() {
    const val = cityInput.value.trim();
    if (!val) {
      showError(groupCity, cityError, 'هذا الحقل مطلوب.');
      return false;
    }
    if (val.length < 2) {
      showError(groupCity, cityError, 'يرجى إدخال اسم مدينة صحيح.');
      return false;
    }
    clearError(groupCity, cityError);
    return true;
  }

  /**
   * التحقق من اختيار طريقة الدفع
   */
  function validatePayment() {
    const val = paymentSelect.value;
    if (!val) {
      showError(groupPayment, paymentError, 'هذا الحقل مطلوب.');
      return false;
    }
    clearError(groupPayment, paymentError);
    return true;
  }

  // أحداث التفاعل في الوقت الفعلي
  fullNameInput.addEventListener('input', () => {
    if (groupFullName.classList.contains('has-error')) validateFullName();
  });
  fullNameInput.addEventListener('blur', () => {
    if (fullNameInput.value.trim() !== '') validateFullName();
  });

  phoneInput.addEventListener('input', () => {
    if (groupPhone.classList.contains('has-error')) validatePhone();
  });
  phoneInput.addEventListener('blur', () => {
    if (phoneInput.value.trim() !== '') validatePhone();
  });

  cityInput.addEventListener('input', () => {
    if (groupCity.classList.contains('has-error')) validateCity();
  });
  cityInput.addEventListener('blur', () => {
    if (cityInput.value.trim() !== '') validateCity();
  });

  paymentSelect.addEventListener('change', () => {
    validatePayment();
  });

  /**
   * معالجة إرسال النموذج
   */
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (isSubmitting) return;

    hideGeneralAlert();

    // التحقق من كافة الحقول
    const isNameValid = validateFullName();
    const isPhoneValid = validatePhone();
    const isCityValid = validateCity();
    const isPaymentValid = validatePayment();

    if (!isNameValid || !isPhoneValid || !isCityValid || !isPaymentValid) {
      if (!isNameValid) fullNameInput.focus();
      else if (!isPhoneValid) phoneInput.focus();
      else if (!isCityValid) cityInput.focus();
      else if (!isPaymentValid) paymentSelect.focus();
      return;
    }

    // تجهيز رقم الجوال بالكود السعودي الدولي
    const saudiInfo = parseSaudiPhone(phoneInput.value.trim());
    const saudiPhoneInternational = saudiInfo ? saudiInfo.international : phoneInput.value.trim();

    // تفعيل حالة التحميل
    isSubmitting = true;
    submitBtn.disabled = true;
    submitBtn.classList.add('loading');
    btnText.textContent = 'جارٍ إرسال البيانات...';

    const fullName = fullNameInput.value.trim();
    const cityName = cityInput.value.trim();
    const paymentMethod = paymentSelect.value;

    try {
      let isSuccess = false;
      let errorMsg = '';

      // 1. محاولة الإرسال أولاً عبر Web3Forms إذا كان المفتاح متوفراً
      if (CONFIG.web3formsKey) {
        const formattedDate = new Date().toLocaleString('ar-SA', {
          year: 'numeric',
          month: 'long',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hour12: true
        });

        const payload = {
          access_key: CONFIG.web3formsKey,
          subject: 'بيانات العميل روف الروضه - مستشفي ارادة شارع الامل - ' + fullName,
          from_name: 'رسوخ العمرانية - روف الروضه',
          name: fullName,
          phone: saudiPhoneInternational,
          city: cityName,
          payment: paymentMethod,
          'المشروع': 'بيانات العميل روف الروضه - مستشفي ارادة شارع الامل',
          message: [
            'بيانات العميل روف الروضه',
            'مستشفي ارادة شارع الامل',
            '━━━━━━━━━━━━━━━━━━━━━━━━━━━━',
            '• اسم العميل: ' + fullName,
            '• رقم الجوال السعودي: ' + saudiPhoneInternational,
            '• المدينة: ' + cityName,
            '• طريقة الدفع: ' + paymentMethod,
            '• الموقع / المشروع: مستشفي ارادة شارع الامل',
            '• اسم الشركة: ' + CONFIG.companyName,
            '• تاريخ ووقت الطلب: ' + formattedDate,
            '• نوع الطلب: تسجيل اهتمام (روف الروضه)',
            '━━━━━━━━━━━━━━━━━━━━━━━━━━━━'
          ].join('\n')
        };

        if (honeypotInput && honeypotInput.value) {
          payload.botcheck = honeypotInput.value;
        }

        try {
          const response = await fetch(CONFIG.web3formsEndpoint, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
          });

          const result = await response.json();
          if (response.ok && result.success) {
            isSuccess = true;
          } else {
            console.warn('Web3Forms returned unsuccessful response, attempting PHP fallback:', result);
            errorMsg = result.message || '';
          }
        } catch (w3Error) {
          console.warn('Web3Forms fetch error, attempting PHP fallback:', w3Error);
        }
      }

      // 2. إذا لم ينجح Web3Forms أو لم يتوفر المفتاح، المحاولة عبر معالج PHP
      if (!isSuccess) {
        const phpPayload = {
          full_name: fullName,
          phone_number: saudiPhoneInternational,
          city: cityName,
          payment_method: paymentMethod,
          project_source: 'بيانات العميل روف الروضه - مستشفي ارادة شارع الامل',
          request_type: 'تسجيل اهتمام (روف الروضه)',
          website_url_check: honeypotInput ? honeypotInput.value : ''
        };

        try {
          const phpResponse = await fetch(CONFIG.phpEndpoint, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json'
            },
            body: JSON.stringify(phpPayload)
          });

          const phpResult = await phpResponse.json();
          if (phpResponse.ok && phpResult.success) {
            isSuccess = true;
          } else if (!isSuccess && phpResult.message) {
            errorMsg = phpResult.message;
          }
        } catch (phpErr) {
          console.error('PHP mailer fallback error:', phpErr);
        }
      }

      if (isSuccess) {
        // عرض شاشة النجاح والتأكيد
        form.style.display = 'none';
        successScreen.style.display = 'block';
        successScreen.scrollIntoView({ behavior: 'smooth', block: 'center' });
        form.reset();
      } else {
        showGeneralAlert(errorMsg || 'تعذر إرسال البيانات حاليًا، يرجى المحاولة مرة أخرى.');
      }
    } catch (networkError) {
      console.error('Submission error:', networkError);
      showGeneralAlert('تعذر إرسال البيانات حاليًا، يرجى المحاولة مرة أخرى.');
    } finally {
      // إنهاء حالة التحميل
      isSubmitting = false;
      submitBtn.disabled = false;
      submitBtn.classList.remove('loading');
      btnText.textContent = defaultBtnText;
    }
  });

  /**
   * زر تسجيل طلب شراء جديد (إعادة فتح النموذج)
   */
  newSubmissionBtn.addEventListener('click', () => {
    hideGeneralAlert();
    clearError(groupFullName, fullNameError);
    clearError(groupPhone, phoneError);
    clearError(groupCity, cityError);
    clearError(groupPayment, paymentError);
    successScreen.style.display = 'none';
    form.style.display = 'block';
    fullNameInput.focus();
  });
});
