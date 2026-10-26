/* ============================================================
   OdinAdmin — sign-in forms (needs js/app.js + js/forms.js for FormValidator).
   Markup reference: login.html, login2.html, register.html, forgot-password.html

   AuthForm   form[data-auth-form].needs-validation
     When FormValidator reports the form valid:
       - the submit button ([data-auth-submit]) is disabled and shows its spinner and
         data-loading-text ("Signing in…")
       - the form is then really submitted (FormValidator itself never posts).
     data-auth-demo  template only: no request; the password "wrong" shows the
                     [data-auth-error] alert, anything else opens data-auth-demo="index.html".
                     A form with a [data-auth-success] block (forgot password) shows that
                     block instead of leaving the page.
     [data-match="#id"] on an input: must equal that input (repeat password).
   In cms/ remove data-auth-demo, set action="{{ route('login') }}" method="POST" and add @csrf.
   ============================================================ */
(function (Odin) {
  'use strict';

  class AuthForm extends Odin.Component {
    static selector = 'form[data-auth-form]';

    init() {
      this.button = this.$('[data-auth-submit]');
      this.error = this.$('[data-auth-error]');
      this.label = this.button && this.button.querySelector('[data-label]');
      this.idleText = this.label ? this.label.textContent : '';

      // "Repeat password": <input data-match="#password"> is invalid until both values are equal
      this.$$('[data-match]').forEach(input => {
        const other = document.querySelector(input.dataset.match);
        if (!other) return;
        const check = () => input.setCustomValidity(input.value && input.value !== other.value ? 'mismatch' : '');
        this.on(input, 'input', check);
        this.on(other, 'input', check);
      });

      this.on(this.el, 'odin:form-invalid', () => this.showError(false));
      this.on(this.el, 'odin:form-valid', () => this.submit());
    }

    setLoading(loading) {
      if (!this.button) return;
      this.button.disabled = loading;
      const spinner = this.button.querySelector('.spinner-border');
      if (spinner) spinner.hidden = !loading;
      if (this.label) this.label.textContent = loading ? this.data('loadingText', 'Please wait…') : this.idleText;
    }

    showError(show, message) {
      if (!this.error) return;
      if (message) this.error.querySelector('.alert-body').textContent = message;
      this.error.hidden = !show;
    }

    submit() {
      this.showError(false);
      this.setLoading(true);
      if (this.el.dataset.authDemo !== undefined) return this.demo();
      this.el.submit(); // native submit: no submit event, so FormValidator does not block it
    }

    /** Template only: simulates the server answer. */
    demo() {
      setTimeout(() => {
        const password = this.$('input[type="password"]');
        if (password && password.value === 'wrong') {
          this.setLoading(false);
          this.showError(true);
          password.focus();
          password.select();
          return;
        }
        const success = this.$('[data-auth-success]');
        if (success) {
          this.setLoading(false);
          success.hidden = false;
          return;
        }
        window.location.href = this.el.dataset.authDemo || 'index.html';
      }, 900);
    }
  }

  Odin.register('authForm', AuthForm);
  Odin.AuthForm = AuthForm;
})(window.Odin);
