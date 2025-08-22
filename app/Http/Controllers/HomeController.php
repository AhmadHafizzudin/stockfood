<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use App\Mail\ContactMail;
use App\Models\DataSetting;
use App\Models\AdminFeature;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\ContactMessage;
use Illuminate\Support\Carbon;
use App\Models\BusinessSetting;
use App\Models\AdminTestimonial;
use Gregwar\Captcha\CaptchaBuilder;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use App\Models\SubscriptionTransaction;
use Illuminate\Support\Facades\Session;
use App\Traits\ActivationClass;

class HomeController extends Controller
{
    use ActivationClass;

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $datas = DataSetting::with('translations')->where('type', 'admin_landing_page')->get();
        $settings = [];

        foreach ($datas as $value) {
            // Translations
            $settings[$value->key] = count($value->translations) > 0 ? $value->translations[0]['value'] : $value->value;
            // Storage
            $settings[$value->key . '_storage'] = count($value->storage) > 0 ? $value->storage[0]['value'] : 'public';
        }

        $business_settings = BusinessSetting::whereIn('key', ['business_name'])->pluck('value', 'key')->toArray();
        $features = AdminFeature::latest()->where('status', 1)->get()->toArray();
        $testimonials = AdminTestimonial::latest()->where('status', 1)->get()->toArray();

        $header_floating_content = json_decode($settings['header_floating_content'] ?? '{}', true);
        $header_image_content = json_decode($settings['header_image_content'] ?? '{}', true);
        $zones = Zone::where('status', 1)->get(['id','name','display_name']);

        // Helper functions for cleaner code
        $get = fn($key, $default = null) => $settings[$key] ?? $default;
        $getStorage = fn($key) => $settings[$key.'_storage'] ?? 'public';
        $getUrl = fn($folder, $key) => Helpers::get_full_url($folder, $get($key), $getStorage($key));

        $landing_data = [
            'header_title' => $get('header_title', 'Why Stay Hungry !'),
            'header_sub_title' => $get('header_sub_title', 'When you can order from'),
            'header_tag_line' => $get('header_tag_line', 'Get Offers'),
            'header_app_button_name' => $get('header_app_button_name', 'Order now'),
            'header_app_button_status' => (int)$get('header_app_button_status', 0),
            'header_button_redirect_link' => $get('header_button_content'),

            'header_floating_total_order' => $header_floating_content['header_floating_total_order'] ?? null,
            'header_floating_total_user' => $header_floating_content['header_floating_total_user'] ?? null,
            'header_floating_total_reviews' => $header_floating_content['header_floating_total_reviews'] ?? null,

            'header_content_image' => $header_image_content['header_content_image'] ?? 'double_screen_image.png',
            'header_content_image_full_url' => Helpers::get_full_url(
                'header_image',
                $header_image_content['header_content_image'] ?? 'double_screen_image.png',
                $header_image_content['header_content_image_storage'] ?? 'public'
            ),
            'header_bg_image' => $header_image_content['header_bg_image'] ?? null,
            'header_bg_image_full_url' => Helpers::get_full_url(
                'header_image',
                $header_image_content['header_bg_image'] ?? null,
                $header_image_content['header_bg_image_storage'] ?? 'public'
            ),

            'about_us_title' => $get('about_us_title'),
            'about_us_sub_title' => $get('about_us_sub_title'),
            'about_us_text' => $get('about_us_text'),
            'about_us_app_button_name' => $get('about_us_app_button_name', 'More'),
            'about_us_app_button_status' => (int)$get('about_us_app_button_status', 0),
            'about_us_redirect_link' => $get('about_us_button_content'),
            'about_us_image_content' => $get('about_us_image_content'),
            'about_us_image_content_full_url' => $getUrl('about_us_image', 'about_us_image_content'),

            'why_choose_us_title' => $get('why_choose_us_title'),
            'why_choose_us_sub_title' => $get('why_choose_us_sub_title'),
        ];

        // Handle why_choose_us images dynamically
        for ($i = 1; $i <= 4; $i++) {
            $landing_data["why_choose_us_image_{$i}"] = $get("why_choose_us_image_{$i}");
            $landing_data["why_choose_us_image_{$i}_full_url"] = $getUrl('why_choose_us_image', "why_choose_us_image_{$i}");
            $landing_data["why_choose_us_title_{$i}"] = $get("why_choose_us_title_{$i}");
        }

        // Features & services
        $landing_data['feature_title'] = $get('feature_title');
        $landing_data['feature_sub_title'] = $get('feature_sub_title');
        $landing_data['features'] = $features;

        $landing_data['services_title'] = $get('services_title');
        $landing_data['services_sub_title'] = $get('services_sub_title');
        for ($i = 1; $i <= 2; $i++) {
            $landing_data["services_order_title_{$i}"] = $get("services_order_title_{$i}");
            $landing_data["services_order_description_{$i}"] = $get("services_order_description_{$i}");
        }
        $landing_data['services_order_button_name'] = $get('services_order_button_name');
        $landing_data['services_order_button_status'] = $get('services_order_button_status');
        $landing_data['services_order_button_link'] = $get('services_order_button_link');

        $landing_data['services_manage_restaurant_title_1'] = $get('services_manage_restaurant_title_1');
        $landing_data['services_manage_restaurant_title_2'] = $get('services_manage_restaurant_title_2');
        $landing_data['services_manage_restaurant_description_1'] = $get('services_manage_restaurant_description_1');
        $landing_data['services_manage_restaurant_description_2'] = $get('services_manage_restaurant_description_2');
        $landing_data['services_manage_restaurant_button_name'] = $get('services_manage_restaurant_button_name');
        $landing_data['services_manage_restaurant_button_status'] = $get('services_manage_restaurant_button_status');
        $landing_data['services_manage_restaurant_button_link'] = $get('services_manage_restaurant_button_link');

        $landing_data['services_manage_delivery_title_1'] = $get('services_manage_delivery_title_1');
        $landing_data['services_manage_delivery_title_2'] = $get('services_manage_delivery_title_2');
        $landing_data['services_manage_delivery_description_1'] = $get('services_manage_delivery_description_1');
        $landing_data['services_manage_delivery_description_2'] = $get('services_manage_delivery_description_2');
        $landing_data['services_manage_delivery_button_name'] = $get('services_manage_delivery_button_name');
        $landing_data['services_manage_delivery_button_status'] = $get('services_manage_delivery_button_status');
        $landing_data['services_manage_delivery_button_link'] = $get('services_manage_delivery_button_link');

        $landing_data['testimonial_title'] = $get('testimonial_title');
        $landing_data['testimonials'] = $testimonials;

        $landing_data['earn_money_title'] = $get('earn_money_title');
        $landing_data['earn_money_sub_title'] = $get('earn_money_sub_title');
        $landing_data['earn_money_reg_title'] = $get('earn_money_reg_title');
        $landing_data['earn_money_restaurant_req_button_name'] = $get('earn_money_restaurant_req_button_name');
        $landing_data['earn_money_restaurant_req_button_status'] = $get('earn_money_restaurant_req_button_status');
        $landing_data['earn_money_delivety_man_req_button_name'] = $get('earn_money_delivety_man_req_button_name');
        $landing_data['earn_money_delivery_man_req_button_status'] = $get('earn_money_delivery_man_req_button_status', 0);
        $landing_data['earn_money_reg_image'] = $get('earn_money_reg_image');
        $landing_data['earn_money_reg_image_full_url'] = $getUrl('earn_money', 'earn_money_reg_image');

        $landing_data['earn_money_delivery_req_button_link'] = $get('earn_money_delivery_man_req_button_link');
        $landing_data['earn_money_restaurant_req_button_link'] = $get('earn_money_restaurant_req_button_link');

        $landing_data['business_name'] = $business_settings['business_name'] ?? 'Stackfood';

        $landing_data['available_zone_status'] = (int)$get('available_zone_status', 0);
        $landing_data['available_zone_title'] = $get('available_zone_title');
        $landing_data['available_zone_short_description'] = $get('available_zone_short_description');
        $landing_data['available_zone_image'] = $get('available_zone_image');
        $landing_data['available_zone_image_full_url'] = $getUrl('available_zone_image', 'available_zone_image');
        $landing_data['available_zone_list'] = $zones;

        // Landing page configuration
        $config = Helpers::get_business_settings('landing_page');
        $landing_integration_type = Helpers::get_business_data('landing_integration_type');
        $redirect_url = Helpers::get_business_data('landing_page_custom_url');

        $new_user = request()?->new_user ?? null;

        if (isset($config) && $config) {
            return view('home', compact('landing_data', 'new_user'));
        } elseif ($landing_integration_type == 'file_upload' && File::exists('resources/views/layouts/landing/custom/index.blade.php')) {
            return view('layouts.landing.custom.index');
        } elseif ($landing_integration_type == 'url') {
            return redirect($redirect_url);
        } else {
            abort(404);
        }
    }

    // Other methods (terms_and_conditions, about_us, contact_us, privacy_policy, etc.) remain unchanged.


    public function terms_and_conditions(Request $request)
    {
        $data = self::get_settings('terms_and_conditions');
        if ($request->expectsJson()) {
            if($request->hasHeader('X-localization')){
                $current_language = $request->header('X-localization');
                $data = self::get_settings_localization('terms_and_conditions',$current_language);
                return response()->json($data);
            }
            return response()->json($data);
        }

        $config = Helpers::get_business_settings('landing_page');
        $landing_integration_type = Helpers::get_business_data('landing_integration_type');
        $redirect_url = Helpers::get_business_data('landing_page_custom_url');

        if(isset($config) && $config){
            return view('terms-and-conditions', compact('data'));
        }elseif($landing_integration_type == 'file_upload'){
            return view('layouts.landing.custom.index');
        }elseif($landing_integration_type == 'url'){
            return redirect($redirect_url);
        }else{
            abort(404);
        }
    }

    public function about_us(Request $request)
    {
        $data = self::get_settings('about_us');
        // $data_title = self::get_settings('about_title');

        if ($request->expectsJson()) {
            if($request->hasHeader('X-localization')){
                $current_language = $request->header('X-localization');
                $data = self::get_settings_localization('about_us',$current_language);
                return response()->json($data);
            }
            return response()->json($data);
        }

        $config = Helpers::get_business_settings('landing_page');
        $landing_integration_type = Helpers::get_business_data('landing_integration_type');
        $redirect_url = Helpers::get_business_data('landing_page_custom_url');

        if(isset($config) && $config){
            return view('about-us', compact('data'));
        }elseif($landing_integration_type == 'file_upload'){
            return view('layouts.landing.custom.index');
        }elseif($landing_integration_type == 'url'){
            return redirect($redirect_url);
        }else{
            abort(404);
        }
    }

    public function contact_us(Request $request)
    {
        if ($request->isMethod('POST')) {
            $request->validate([
                'name' => 'required',
                'email' => 'required|email:filter',
                'message' => 'required',
            ],[
                'name.required' => translate('messages.Name is required!'),
                'email.required' => translate('messages.Email is required!'),
                'email.filter' => translate('messages.Must ba a valid email!'),
                'message.required' => translate('messages.Message is required!'),
            ]);

            $recaptcha = Helpers::get_business_settings('recaptcha');
            if (isset($recaptcha) && $recaptcha['status'] == 1 && !$request?->set_default_captcha) {
                $request->validate([
                    'g-recaptcha-response' => [
                        function ($attribute, $value, $fail) {
                            $secret_key = Helpers::get_business_settings('recaptcha')['secret_key'];
                            $gResponse = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                                'secret' => $secret_key,
                                'response' => $value,
                                'remoteip' => \request()->ip(),
                            ]);

                            if (!$gResponse->successful()) {
                                $fail(translate('ReCaptcha Failed'));
                            }
                        },
                    ],
                ]);
            } else if (strtolower(session('six_captcha')) != strtolower($request->custome_recaptcha)) {
                Toastr::error(translate('messages.ReCAPTCHA Failed'));
                return back();
            }

            $email = Helpers::get_settings('email_address');
            $messageData = [
                'name' => $request->name,
                'email' => $request->email,
                'message' => $request->message,
            ];
            ContactMessage::create($messageData);

            $business_name=Helpers::get_settings('business_name') ?? 'Stackfood';
            $subject='Enquiry from '.$business_name;
            try{
                if(config('mail.status')) {
                    Mail::to($email)->send(new ContactMail($messageData,$subject));
                    Toastr::success(translate('messages.Thanks_for_your_enquiry._We_will_get_back_to_you_soon.'));
                }
            }catch(\Exception $exception)
            {
                dd([$exception->getFile(),$exception->getLine(),$exception->getMessage()]);
            }
            return back();
        }


        $config = Helpers::get_business_settings('landing_page');
        $landing_integration_type = Helpers::get_business_data('landing_integration_type');
        $redirect_url = Helpers::get_business_data('landing_page_custom_url');

        $custome_recaptcha = new CaptchaBuilder;
        $custome_recaptcha->build();
        Session::put('six_captcha', $custome_recaptcha->getPhrase());

        if(isset($config) && $config){
            return view('contact-us',compact('custome_recaptcha'));
        }elseif($landing_integration_type == 'file_upload'){
            return view('layouts.landing.custom.index');
        }elseif($landing_integration_type == 'url'){
            return redirect($redirect_url);
        }else{
            abort(404);
        }
    }

    public function privacy_policy(Request $request)
    {
        $data = self::get_settings('privacy_policy');
        if ($request->expectsJson()) {
            if($request->hasHeader('X-localization')){
                $current_language = $request->header('X-localization');
                $data = self::get_settings_localization('privacy_policy',$current_language);
                return response()->json($data);
            }
            return response()->json($data);
        }
        $config = Helpers::get_business_settings('landing_page');
        $landing_integration_type = Helpers::get_business_data('landing_integration_type');
        $redirect_url = Helpers::get_business_data('landing_page_custom_url');

        if(isset($config) && $config){
            return view('privacy-policy',compact('data'));
        }elseif($landing_integration_type == 'file_upload'){
            return view('layouts.landing.custom.index');
        }elseif($landing_integration_type == 'url'){
            return redirect($redirect_url);
        }else{
            abort(404);
        }
    }

    public function refund_policy(Request $request)
    {
        $data = self::get_settings('refund_policy');
        $status = self::get_settings('refund_policy_status');
        if ($request->expectsJson()) {
            if($request->hasHeader('X-localization')){
                $current_language = $request->header('X-localization');
                $data = self::get_settings_localization('refund_policy',$current_language);
                return response()->json($data);
            }
            return response()->json($data);
        }
        abort_if($status == 0 ,404);

        $config = Helpers::get_business_settings('landing_page');
        $landing_integration_type = Helpers::get_business_data('landing_integration_type');
        $redirect_url = Helpers::get_business_data('landing_page_custom_url');

        if(isset($config) && $config){
            return view('refund_policy',compact('data'));
        }elseif($landing_integration_type == 'file_upload'){
            return view('layouts.landing.custom.index');
        }elseif($landing_integration_type == 'url'){
            return redirect($redirect_url);
        }else{
            abort(404);
        }
    }

    public function shipping_policy(Request $request)
    {
        $data = self::get_settings('shipping_policy');
        $status = self::get_settings('shipping_policy_status');
        if ($request->expectsJson()) {
            if($request->hasHeader('X-localization')){
                $current_language = $request->header('X-localization');
                $data = self::get_settings_localization('shipping_policy',$current_language);
                return response()->json($data);
            }
            return response()->json($data);
        }
        abort_if($status == 0 ,404);

        $config = Helpers::get_business_settings('landing_page');
        $landing_integration_type = Helpers::get_business_data('landing_integration_type');
        $redirect_url = Helpers::get_business_data('landing_page_custom_url');

        if(isset($config) && $config){
            return view('shipping_policy',compact('data'));
        }elseif($landing_integration_type == 'file_upload'){
            return view('layouts.landing.custom.index');
        }elseif($landing_integration_type == 'url'){
            return redirect($redirect_url);
        }else{
            abort(404);
        }
    }

    public function cancellation_policy(Request $request)
    {
        $data = self::get_settings('cancellation_policy');
        $status = self::get_settings('cancellation_policy_status');
        if ($request->expectsJson()) {
            if($request->hasHeader('X-localization')){
                $current_language = $request->header('X-localization');
                $data = self::get_settings_localization('cancellation_policy',$current_language);
                return response()->json($data);
            }
            return response()->json($data);
        }
        abort_if($status == 0 ,404);

        $config = Helpers::get_business_settings('landing_page');
        $landing_integration_type = Helpers::get_business_data('landing_integration_type');
        $redirect_url = Helpers::get_business_data('landing_page_custom_url');

        if(isset($config) && $config){
            return view('cancellation_policy',compact('data'));
        }elseif($landing_integration_type == 'file_upload'){
            return view('layouts.landing.custom.index');
        }elseif($landing_integration_type == 'url'){
            return redirect($redirect_url);
        }else{
            abort(404);
        }
    }

    public static function get_settings($name)
    {
        $data = DataSetting::where(['key' => $name])->first()?->value;
        return $data;
    }


    public function lang($local)
    {
        $direction = BusinessSetting::where('key', 'site_direction')->first();
        $direction = $direction->value ?? 'ltr';
        $language = BusinessSetting::where('key', 'system_language')->first();
        foreach (json_decode($language['value'], true) as $key => $data) {
            if ($data['code'] == $local) {
                $direction = isset($data['direction']) ? $data['direction'] : 'ltr';
            }
        }
        session()->forget('landing_language_settings');
        Helpers::landing_language_load();
        session()->put('landing_site_direction', $direction);
        session()->put('landing_local', $local);
        return redirect()->back();
    }
    public static function get_settings_localization($name,$lang)
    {
        $config = null;
        $data = DataSetting::withoutGlobalScope('translate')->with(['translations' => function ($query) use ($lang) {
            return $query->where('locale', $lang);
        }])->where(['key' => $name])->first();
        if($data && count($data->translations)>0){
            $data = $data->translations[0]['value'];
        }else{
            $data = $data ? $data->value: '';
        }
        return $data;
    }

    public function maintenanceMode(){

        $maintenance = Cache::get('maintenance');


        if(!Cache::has('maintenance') ||  $maintenance['restaurant_panel'] == false  ){
            return to_route('home');
        }

        elseif (isset($maintenance['start_date']) && isset($maintenance['end_date'])) {
            $start = Carbon::parse($maintenance['start_date']);
            $end = Carbon::parse($maintenance['end_date']);
            $today = Carbon::now();
            if($today->gt($end)){
                return to_route('home');
            }
        }

        $maintenance_mode_data=   \App\Models\DataSetting::where('type','maintenance_mode')->whereIn('key' ,['maintenance_message_setup'])->pluck('value','key')
        ->map(function ($value) {
            return json_decode($value, true);
        })
        ->toArray();

                $selectedMaintenanceMessage     = data_get($maintenance_mode_data,'maintenance_message_setup',[]);


        $email = Helpers::get_business_data('email_address');
        $phone = Helpers::get_business_data('phone');


        return view('maintenance-mode',compact('email','phone','selectedMaintenanceMessage'));
    }

    public function subscription_invoice($id){

        $id= base64_decode($id);
        $BusinessData= ['admin_commission' ,'business_name','address','phone','logo','email_address'];
        $transaction= SubscriptionTransaction::with(['restaurant.vendor','package:id,package_name,price'])->findOrFail($id);
        $BusinessData=BusinessSetting::whereIn('key', $BusinessData)->pluck('value' ,'key') ;
        $logo=BusinessSetting::where('key', "logo")->first() ;
        $mpdf_view = View::make('subscription-invoice', compact('transaction','BusinessData','logo'));
        Helpers::gen_mpdf(view: $mpdf_view,file_prefix: 'Subscription',file_postfix: $id);
        return back();
    }


    public function getActivationCheckView(Request $request)
    {
        return view('installation.activation-check');
    }

    public function activationCheck(Request $request)
    {
        $response = $this->getRequestConfig(
            username: $request['username'],
            purchaseKey: $request['purchase_key'],
            softwareType: $request->get('software_type', base64_decode('cHJvZHVjdA=='))
        );
        $this->updateActivationConfig(app: 'admin_panel', response: $response);
        return redirect(url('/'));
    }
}
