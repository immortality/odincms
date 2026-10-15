{{--
    The main button of a sign-in style form: shows a spinner while AuthForm (odinadmin/js/auth.js) posts.
    <x-admin.auth.submit>{{ __('Sign in') }}</x-admin.auth.submit>
--}}
<button type="submit" {{ $attributes->class(['btn-brand btn-lg btn-block']) }} data-auth-submit>
    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" hidden></span>
    <span data-label>{{ $slot }}</span>
</button>
