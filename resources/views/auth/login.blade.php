<!DOCTYPE html>
    <?php
    $log_email_succ = session()->get('log_email_succ');
    ?>
<html dir="{{ $site_direction }}" lang="{{ $locale }}" class="{{ $site_direction === 'rtl'?'active':'' }}">
<head>
    <!-- Required Meta Tags Always Come First -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    @php
        $app_name = \App\CentralLogics\Helpers::get_business_settings('business_name', false);
        $icon = \App\CentralLogics\Helpers::get_business_settings('icon', false);
    @endphp
    <!-- Title -->
    @if(in_array($role, ['admin', 'admin_employee']))
        <title>{{ translate('messages.admin_login') }} | {{$app_name??translate('STACKFOOD')}}</title>
    @elseif(in_array($role, ['vendor', 'vendor_employee']))
        <title>{{ translate('messages.restaurant_login') }} | {{$app_name??translate('STACKFOOD')}}</title>
    @else
        <title>{{ translate('messages.login') }} | {{$app_name??translate('STACKFOOD')}}</title>
    @endif

    <!-- Favicon -->
    <link rel="shortcut icon" href="{{asset($icon ? 'storage/app/public/business/'.$icon : 'public/favicon.ico')}}">

    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&amp;display=swap" rel="stylesheet">
    <!-- CSS Implementing Plugins -->
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin')}}/css/vendor.min.css">
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin')}}/vendor/icon-set/style.css">
    <!-- CSS Front Template -->
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin')}}/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin')}}/css/theme.minc619.css?v=1.0">
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin')}}/css/style.css">
    <link rel="stylesheet" href="{{dynamicAsset('public/assets/admin')}}/css/toastr.css">
</head>

<body>
<!-- ========== MAIN CONTENT ========== -->
@php
    $loginTypeClass = '';
    if(in_array($role, ['admin', 'admin_employee'])) {
        $loginTypeClass = 'admin-login';
    } elseif(in_array($role, ['vendor', 'vendor_employee'])) {
        $loginTypeClass = 'restaurant-login';
    }
@endphp
<main id="content" role="main" class="main auth-bg {{ $loginTypeClass }}">
    <!-- Content -->
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div class="auth-content">
            <div class="content">
                @if(in_array($role, ['admin', 'admin_employee']))
                    <h2 class="title text-uppercase">{{translate('messages.welcome_to')}} {{ $app_name??'STACKFOOD' }} {{translate('messages.admin_panel')}}</h2>
                    <p>
                        {{translate('messages.manage_your_platform_efficiently')}}
                    </p>
                    <div class="mt-3">
                        <span class="badge badge-primary">{{translate('messages.admin_access')}}</span>
                    </div>
                @elseif(in_array($role, ['vendor', 'vendor_employee']))
                    <h2 class="title text-uppercase">{{translate('messages.welcome_to')}} {{ $app_name??'STACKFOOD' }} {{translate('messages.restaurant_panel')}}</h2>
                    <p>
                        {{translate('messages.manage_your_restaurant_operations')}}
                    </p>
                    <div class="mt-3">
                        <span class="badge badge-success">{{translate('messages.restaurant_access')}}</span>
                    </div>
                @else
                    <h2 class="title text-uppercase">{{translate('messages.welcome_to')}} {{ $app_name??'STACKFOOD' }}</h2>
                    <p>
                        {{translate('Manage_your_app_&_website_easily')}}
                    </p>
                @endif
            </div>
        </div>
        <div class="auth-wrapper">
            <div class="auth-wrapper-body auth-form-appear">
                @php($systemlogo=\App\Models\BusinessSetting::where(['key'=>'logo'])->first())
                @php($role = $role ?? null )
                <a class="auth-logo mb-3" href="javascript:">
                    <img class="z-index-2 onerror-image"
                    src="{{ \App\CentralLogics\Helpers::get_full_url('business',$systemlogo?->value,$systemlogo?->storage[0]?->value ?? 'public', 'authfav') }}"
                    data-onerror-image="{{ dynamicAsset('/public/assets/admin/img/auth-fav.png') }}" alt="image">
                </a>
                
                <!-- Login Type Switcher -->
                <div class="text-center mb-4">
                    <div class="login-type-switcher">
                        <a href="{{ url('/login/admin') }}" class="login-switch-btn {{ in_array($role, ['admin', 'admin_employee']) ? 'active' : '' }}">
                            <i class="tio-settings"></i> {{translate('messages.admin')}}
                        </a>
                        <a href="{{ url('/login/restaurant') }}" class="login-switch-btn {{ in_array($role, ['vendor', 'vendor_employee']) ? 'active' : '' }}">
                            <i class="tio-shop"></i> {{translate('messages.restaurant')}}
                        </a>
                    </div>
                </div>
                
                <div class="text-center">
                    <div class="auth-header mb-5">
                        @if(in_array($role, ['admin', 'admin_employee']))
                            <h2 class="signin-txt">{{ translate('messages.signin_to_admin_panel')}}</h2>
                            <p class="text-muted">{{translate('messages.access_admin_dashboard')}}</p>
                        @elseif(in_array($role, ['vendor', 'vendor_employee']))
                            <h2 class="signin-txt">{{ translate('messages.signin_to_restaurant_panel')}}</h2>
                            <p class="text-muted">{{translate('messages.access_restaurant_dashboard')}}</p>
                        @else
                            <h2 class="signin-txt">{{ translate('messages.Signin_To_Your_Panel')}}</h2>
                        @endif
                    </div>
                </div>
                <!-- Content -->
                <label class="badge badge-soft-success float-right initial-1">
                    {{translate('messages.software_version')}} : {{env('SOFTWARE_VERSION')}}
                </label>
                <!-- Form -->
                <form class="login_form" action="{{route('login_post')}}" method="post" id="form-id">
                    @csrf
                    <input type="hidden" name="role" value="{{  $role ?? null }}">

                    <div class="js-form-message form-group mb-2">
                        <label class="form-label text-capitalize" for="signinSrEmail">{{translate('messages.your_email')}}</label>
                        <input type="email" class="form-control form-control-lg" value="{{ $email ?? '' }}" name="email" id="signinSrEmail"
                            tabindex="1" aria-label="email@address.com"
                            required data-msg="Please enter a valid email address.">
                        <div class="focus-effects"></div>
                    </div>
                    <!-- End Form Group -->

                    <!-- Form Group -->
                    <div class="js-form-message form-group">
                        <label class="form-label text-capitalize" for="signupSrPassword" tabindex="0">
                            <span class="d-flex justify-content-between align-items-center">
                            {{translate('messages.password')}}
                            </span>
                        </label>
                        <div class="input-group input-group-merge">
                            <input type="password" class="js-toggle-password form-control form-control-lg __rounded"
                                name="password" id="signupSrPassword" value="{{ $password ?? '' }}"
                                aria-label="{{translate('messages.password_length_placeholder',['length'=>'6+'])}}" required
                                data-msg="{{translate('messages.invalid_password_warning')}}"
                                data-hs-toggle-password-options='{
                                            "target": "#changePassTarget",
                                    "defaultClass": "tio-hidden-outlined",
                                    "showClass": "tio-visible-outlined",
                                    "classChangeTarget": "#changePassIcon"
                                    }'>

                            <div class="focus-effects"></div>
                            <div id="changePassTarget" class="input-group-append">
                                <a class="input-group-text" href="javascript:">
                                    <i id="changePassIcon" class="tio-visible-outlined"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- End Form Group -->
                        <div class="mb-2"></div>
                        <div class="d-flex justify-content-between mt-5">
                    <!-- Checkbox -->
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="termsCheckbox" {{ $password ? 'checked' : '' }}
                                    name="remember">
                                <label class="custom-control-label text-muted" for="termsCheckbox">
                                    {{translate('messages.remember_me')}}
                                </label>
                            </div>
                        </div>
                    <!-- End Checkbox -->
                    <!-- forget password -->
                        <div class="form-group {{ $role == 'admin' ? '' : 'd-none' }}"  id="forget-password">
                            <div class="custom-control">
                                <span type="button" data-toggle="modal" data-target="#forgetPassModal">{{ translate('Forget_Password?') }}</span>
                            </div>
                        </div>
                        <div class="form-group {{ $role == 'vendor' ? '' : 'd-none' }}"  id="forget-password1">
                            <div class="custom-control">
                                <span type="button" data-toggle="modal" data-target="#forgetPassModal1">{{ translate('Forget_Password?') }}</span>
                            </div>
                        </div>
                    </div>
                    <!-- End forget password -->


                    {{-- Kawanku Admin Captcha Function - Captcha display section --}}
                    {{--
                    @php($recaptcha = \App\CentralLogics\Helpers::get_business_settings('recaptcha'))
                    @if(isset($recaptcha) && $recaptcha['status'] == 1)
                        <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">

                        <input type="hidden" name="set_default_captcha" id="set_default_captcha_value" value="0" >
                        <div class="row p-2 d-none" id="reload-captcha">
                            <div class="col-6 pr-0">
                                <input type="text" class="form-control form-control-lg border-0" name="custome_recaptcha"
                                       id="custome_recaptcha" required placeholder="{{translate('Enter recaptcha value')}}" autocomplete="off" value="{{env('APP_MODE')=='dev'? session('six_captcha'):''}}"> 
                            </div>
                            <div class="col-6 bg-white rounded d-flex">
                                <img src="<?php echo $custome_recaptcha->inline(); ?>" class="rounded w-100" />
                                <div class="p-3 pr-0 capcha-spin reloadCaptcha">
                                    <i class="tio-cached"></i>
                                </div>
                            </div>
                        </div>

                    @else
                        <div class="row p-2" id="reload-captcha">
                            <div class="col-6 pr-0">
                                <input type="text" class="form-control form-control-lg border-0" name="custome_recaptcha"
                                       id="custome_recaptcha" required placeholder="{{translate('Enter recaptcha value')}}" autocomplete="off" value="{{env('APP_MODE')=='dev'? session('six_captcha'):''}}"> 
                            </div>
                            <div class="col-6 bg-white rounded d-flex">
                                <img src="<?php echo $custome_recaptcha->inline(); ?>" class="rounded w-100" />
                                <div class="p-3 pr-0 capcha-spin reloadCaptcha">
                                    <i class="tio-cached"></i>
                                </div>
                            </div>
                        </div>
                    @endif
                    --}}

                    <button type="submit" class="btn btn-lg btn-block btn-primary" id="signInBtn">{{translate('messages.sign_in')}}</button>
                </form>
                <!-- End Form -->

                <!-- End Content -->
            </div>
            @if(env('APP_MODE') =='demo' )
                @if (isset($role) &&  $role == 'admin')
                    <div class="auto-fill-data-copy">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <div>
                                <span class="d-block"><strong>Email</strong> : admin@admin.com</span>
                                <span class="d-block"><strong>Password</strong> : 12345678</span>
                            </div>
                            <div>
                                <button class="btn btn-primary m-0" id="copy_cred"><i class="tio-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
                @if (isset($role) &&  $role == 'vendor')
                    <div class="auto-fill-data-copy">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <div>
                                <span class="d-block"><strong>Email</strong> : test.restaurant@gmail.com</span>
                                <span class="d-block"><strong>Password</strong> : 12345678</span>
                            </div>
                            <div>
                                <button class="btn btn-primary m-0" id="copy_cred2"><i class="tio-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</main>
<!-- ========== END MAIN CONTENT ========== -->


<div class="modal fade" id="forgetPassModal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header justify-content-end">
          <span type="button" class="close-modal-icon" data-dismiss="modal">
              <i class="tio-clear"></i>
          </span>
        </div>
        <div class="modal-body">
          <div class="forget-pass-content">
              <img src="{{dynamicAsset('/public/assets/admin/img/send-mail.svg')}}" alt="">
              <!-- After Succeed -->
              <h4>
                  {{ translate('Send_Mail_to_Your_Email_?') }}
              </h4>
              <p>
                  {{ translate('A_mail_will_be_send_to_your_registered_email_with_a_link_to_change_passowrd') }}
              </p>
              <a class="btn btn-lg btn-block btn--primary mt-3" href="{{route('reset-password')}}">
                  {{ translate('Send_Mail') }}
              </a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="forgetPassModal1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header justify-content-end">
          <span type="button" class="close-modal-icon" data-dismiss="modal">
              <i class="tio-clear"></i>
          </span>
        </div>
        <div class="modal-body">
          <div class="forget-pass-content">
              <img src="{{dynamicAsset('/public/assets/admin/img/send-mail.svg')}}" alt="">
              <!-- After Succeed -->
              <h4>
                  {{ translate('messages.Send_Mail_to_Your_Email_?') }}
              </h4>
              <form class="" action="{{ route('vendor-reset-password') }}" method="post">
                  @csrf

                  <input type="email" name="email" id="" class="form-control" required>
                  <button type="submit" class="btn btn-lg btn-block btn--primary mt-3">{{ translate('messages.Send_Mail') }}</button>
              </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="successMailModal">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header justify-content-end">
            <span type="button" class="close-modal-icon" data-dismiss="modal">
                <i class="tio-clear"></i>
            </span>
          </div>
          <div class="modal-body">
            <div class="forget-pass-content">
                <!-- After Succeed -->
                <img src="{{dynamicAsset('/public/assets/admin/img/sent-mail.svg')}}" alt="">
                <h4>
                  {{ translate('A_mail_has_been_sent_to_your_registered_email') }}!
                </h4>
                <p>
                  {{ translate('Click_the_link_in_the_mail_description_to_change_password') }}
                </p>
            </div>
          </div>
        </div>
      </div>
    </div>


<!-- JS Implementing Plugins -->
<script src="{{dynamicAsset('public/assets/admin')}}/js/vendor.min.js"></script>

<!-- JS Front -->
<script src="{{dynamicAsset('public/assets/admin')}}/js/theme.min.js"></script>
<script src="{{dynamicAsset('public/assets/admin')}}/js/toastr.js"></script>
{!! Toastr::message() !!}

@if ($errors->any())
    <script>
        @foreach($errors->all() as $error)
        toastr.error('{{translate($error)}}', Error, {
            CloseButton: true,
            ProgressBar: true
        });
        @endforeach
    </script>
@endif
@if ($log_email_succ)
@php(session()->forget('log_email_succ'))
    <script>
        $('#successMailModal').modal('show');
    </script>
@endif

<script>
    // $("#forget-password").hide();
      $("#role-select").change(function() {
        var selectValue = $(this).val();
        if (selectValue == "admin") {
          $("#forget-password").show();
          $("#forget-password1").hide();
        } else if(selectValue == "vendor") {
          $("#forget-password").hide();
          $("#forget-password1").show();
        }
        else {
          $("#forget-password").hide();
          $("#forget-password1").hide();
        }
      });
</script>


<script>
    // Kawanku Admin Captcha Function - Captcha reload JavaScript
    /*
    $(document).on('click','.reloadCaptcha', function(){
        $.ajax({
            url: "{{ route('reload-captcha') }}",
            type: "GET",
            dataType: 'json',
            beforeSend: function () {
                $('#loading').show()
                $('.capcha-spin').addClass('active')
            },
            success: function(data) {
                $('#reload-captcha').html(data.view);
            },
            complete: function () {
                $('#loading').hide()
                $('.capcha-spin').removeClass('active')
            }
        });
    });
    */
</script>
<!-- JS Plugins Init. -->
<script>
    $(document).on('ready', function () {
        // INITIALIZATION OF SHOW PASSWORD
        // =======================================================
        $('.js-toggle-password').each(function () {
            new HSTogglePassword(this).init()
        });

        // INITIALIZATION OF FORM VALIDATION
        // =======================================================
        $('.js-validate').each(function () {
            $.HSCore.components.HSValidation.init($(this));
        });
    });
</script>

{{-- Kawanku Admin Captcha Function - Google reCAPTCHA script section --}}
{{--
@if(isset($recaptcha) && $recaptcha['status'] == 1)
    <script src="https://www.google.com/recaptcha/api.js?render={{$recaptcha['site_key']}}"></script>
@endif
@if(isset($recaptcha) && $recaptcha['status'] == 1)
    <script>
        $(document).ready(function() {
            $('#signInBtn').click(function (e) {
                if( $('#set_default_captcha_value').val() == 1){
                    $('#form-id').submit();
                    return true;
                }
                e.preventDefault();
                if (typeof grecaptcha === 'undefined') {
                    toastr.error('Invalid recaptcha key provided. Please check the recaptcha configuration.');
                    $('#reload-captcha').removeClass('d-none');
                    $('#set_default_captcha_value').val('1');

                    return;
                }
                grecaptcha.ready(function () {
                    grecaptcha.execute('{{$recaptcha['site_key']}}', {action: 'submit'}).then(function (token) {
                        $('#g-recaptcha-response').value = token;
                        $('#form-id').submit();
                    });
                });
                window.onerror = function (message) {
                    var errorMessage = 'An unexpected error occurred. Please check the recaptcha configuration';
                    if (message.includes('Invalid site key')) {
                        errorMessage = 'Invalid site key provided. Please check the recaptcha configuration.';
                    } else if (message.includes('not loaded in api.js')) {
                        errorMessage = 'reCAPTCHA API could not be loaded. Please check the recaptcha API configuration.';
                    }
                    $('#reload-captcha').removeClass('d-none');
                    $('#set_default_captcha_value').val('1');
                    toastr.error(errorMessage)
                    return true;
                };
            });
        });
    </script>
@endif
--}}
{{-- recaptcha scripts end --}}



@if(env('APP_MODE') =='demo')
    <script>
        $("#copy_cred").click(function() {
            $('#signinSrEmail').val('admin@admin.com');
            $('#signupSrPassword').val('12345678');
            toastr.success('Copied successfully!', 'Success!', {
                CloseButton: true,
                ProgressBar: true
            });
        })
        $("#copy_cred2").click(function() {
            $('#signinSrEmail').val('test.restaurant@gmail.com');
            $('#signupSrPassword').val('12345678');
            toastr.success('Copied successfully!', 'Success!', {
                CloseButton: true,
                ProgressBar: true
            });
        })
    </script>
@endif

<!-- Custom Login Type Styles -->
<style>
    /* Login Type Switcher Styles */
    .login-type-switcher {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .login-switch-btn {
        padding: 8px 16px;
        border-radius: 20px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        background: rgba(255, 255, 255, 0.1);
        color: #666;
        backdrop-filter: blur(10px);
    }
    
    .login-switch-btn:hover {
        text-decoration: none;
        transform: translateY(-2px);
    }
    
    .login-switch-btn i {
        margin-right: 5px;
    }
    
    /* Admin Login Styles */
    .admin-login .auth-wrapper {
        border-top: 4px solid #667eea;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
    }
    
    .admin-login .auth-logo img {
        filter: drop-shadow(0 4px 8px rgba(102, 126, 234, 0.3));
    }
    
    .admin-login .signin-txt {
        color: #667eea;
    }
    
    .admin-login .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
    }
    
    .admin-login .btn-primary:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        transform: translateY(-2px);
    }
    
    .admin-login .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .admin-login .login-switch-btn.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
    }
    
    .admin-login .login-switch-btn:not(.active):hover {
        border-color: #667eea;
        color: #667eea;
    }
    
    /* Restaurant Login Styles */
    .restaurant-login .auth-wrapper {
        border-top: 4px solid #f5576c;
        box-shadow: 0 10px 30px rgba(245, 87, 108, 0.2);
    }
    
    .restaurant-login .auth-logo img {
        filter: drop-shadow(0 4px 8px rgba(245, 87, 108, 0.3));
    }
    
    .restaurant-login .signin-txt {
        color: #f5576c;
    }
    
    .restaurant-login .btn-primary {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        border: none;
    }
    
    .restaurant-login .btn-primary:hover {
        background: linear-gradient(135deg, #ee7fe9 0%, #f3455a 100%);
        transform: translateY(-2px);
    }
    
    .restaurant-login .form-control:focus {
        border-color: #f5576c;
        box-shadow: 0 0 0 0.2rem rgba(245, 87, 108, 0.25);
    }
    
    .restaurant-login .login-switch-btn.active {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        border-color: #f5576c;
    }
    
    .restaurant-login .login-switch-btn:not(.active):hover {
        border-color: #f5576c;
        color: #f5576c;
    }
    
    .admin-login .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .restaurant-login .form-control:focus {
        border-color: #f5576c;
        box-shadow: 0 0 0 0.2rem rgba(245, 87, 108, 0.25);
    }
    
    .auth-content .badge {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
    }
</style>

<!-- IE Support -->
<script>
    if (/MSIE \d|Trident.*rv:/.test(navigator.userAgent)) document.write('<script src="{{dynamicAsset('public//assets/admin')}}/vendor/babel-polyfill/polyfill.min.js"><\/script>');
</script>
</body>
</html>
