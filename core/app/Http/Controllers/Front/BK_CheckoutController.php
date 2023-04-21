<?php

namespace App\Http\Controllers\Front;

use App\{
    Models\Order,
    Models\PaymentSetting,
    Traits\StripeCheckout,
    Traits\MollieCheckout,
    Traits\PaypalCheckout,
    Traits\PaystackCheckout,
    Http\Controllers\Controller,
    Http\Requests\PaymentRequest,
    Traits\CashOnDeliveryCheckout,
    Traits\BankCheckout,
    Repositories\Front\USPSRepository
};
use UpsRate;
use App\Helpers\PriceHelper;
use App\Helpers\SmsHelper;
use App\Models\Currency;
use App\Models\Item;
use App\Models\Setting;
use App\Models\ShippingService;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Mollie\Laravel\Facades\Mollie;
use USPS\RatePackage;
use Log;

class CheckoutController extends Controller
{
    use StripeCheckout {
        StripeCheckout::__construct as private __stripeConstruct;
    }

    use PaypalCheckout {
        PaypalCheckout::__construct as private __paypalConstruct;
    }

    use MollieCheckout {
        MollieCheckout::__construct as private __MollieConstruct;
    }

    use BankCheckout;
    use PaystackCheckout;
    use CashOnDeliveryCheckout;

    public function __construct()
    {
        $setting = Setting::first();

        if ($setting->is_guest_checkout != 1) {
            $this->middleware('auth');
        }
        
        $this->middleware('localize');
        $this->__stripeConstruct();
        $this->__paypalConstruct();
    }

    public function testUsps()
    {
        $rate = new \USPS\Rate('824AWACH7401');
        $package = new RatePackage();
        //$package->setService('PARCEL SELECT GROUND');
        $package->setService('ALL');
        $package->setFirstClassMailType(RatePackage::MAIL_TYPE_PACKAGE);
        $package->setZipOrigination(30253);
        $package->setZipDestination(30252); //30008
        $package->setPounds(0);
        $package->setOunces(1.5);
        $package->setContainer('');
        $package->setSize(RatePackage::SIZE_REGULAR);
        $package->setField('Machinable', true);

        // add the package to the rate stack
        $rate->addPackage($package);

        // Perform the request and print out the result
        echo '<pre>';
        print_r($rate->getRate());
        echo '</pre>';
        echo '<pre>';
        print_r($rate->getArrayResponse());
        echo '</pre>';

        // Was the call successful
        if ($rate->isSuccess()) {
            echo 'Done';
        } else {
            echo 'Error: '.$rate->getErrorMessage();
        }
    }
    
	public function ship_address()
	{
        if (!Session::has('cart')) {
            return redirect(route('front.cart'));
        }

        $data['user'] = Auth::user() ? Auth::user() : null;
        $cart = Session::get('cart');
        $total_tax = 0;
        $cart_total = 0;
        $total = 0;
        
        foreach ($cart as $key => $item) {
            $total += ($item['main_price'] + $item['attribute_price']) * $item['qty'];
            $cart_total = $total;
            $item = Item::findOrFail($key);
            if ($item->tax) {
                $total_tax += $item::taxCalculate($item);
            }
        }
        
        /*$shipping = [];
        if(ShippingService::whereStatus(1)->whereId(1)->whereIsCondition(1)->exists()){
            $shipping = ShippingService::whereStatus(1)->whereId(1)->whereIsCondition(1)->first();
            if ($cart_total >= $shipping->minimum_price) {
                $shipping = $shipping;
            } else {
                $shipping = [];
            }
        }

        if (!$shipping) {
            $shipping = ShippingService::whereStatus(1)->where('id','!=',1)->first();
        }
        */

        $discount = [];
        if (Session::has('coupon')) {
            $discount = Session::get('coupon');
        }

        if (!PriceHelper::Digital()) {
            $shipping = null;
        }

        /**
         * UPS API -> Get shipping rates
        */
        $shipping = null;
        if (Session::has('shipping_address')) {
            // creaee UPS Repository Object
            $upsObj = new USPSRepository();
            $code = Session::has('shippingServiceCode') ? Session::get('shippingServiceCode') : '1';
            $data['shipping'] = $shipping = $upsObj->getShippingRate($cart, $code);
        }

        $grand_total = ($cart_total + ($shipping && $shipping['success'] ? $shipping['rate']['Postage']['Rate'] : 0)) + $total_tax;
        
        $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);
        $state_tax = Auth::check() && Auth::user()->state_id ? Auth::user()->state->price : 0;
        $total_amount = $grand_total + $state_tax;

        $data['cart'] = $cart;
        $data['cart_total'] = $cart_total;
        $data['grand_total'] = $total_amount;
        $data['discount'] = $discount;
        $data['shipping'] = $shipping;
        $data['tax'] = $total_tax;
        $data['payments'] = PaymentSetting::whereStatus(1)->get();
        
        return view('front.checkout.billing', $data);
    }

    public function billingStore(Request $request)
    {
        Session::put('billing_address', $request->all());
        Session::forget('shipping_address');

        // store billing info into database
        if ($request->user()) {
            $user = $request->user();
            $user->bill_company = $request->bill_company;
            $user->bill_address1 = $request->bill_address1;
            $user->bill_address2 = $request->bill_address2;
            $user->bill_zip = $request->bill_zip;
            $user->bill_city = $request->bill_city;
            $user->bill_country = $request->bill_country;
            $user->save();
        }

        if ($request->same_ship_address) {
            if (PriceHelper::CheckDigital()) {
                $shipping = [
                    "ship_first_name" => $request->bill_first_name,
                    "ship_last_name" => $request->bill_last_name,
                    "ship_email" => $request->bill_email,
                    "ship_phone" => $request->bill_phone,
                    "ship_company" => $request->bill_company,
                    "ship_address1" => $request->bill_address1,
                    "ship_address2" => $request->bill_address2,
                    "ship_zip" => $request->bill_zip,
                    "ship_city" => $request->bill_city,
                    "ship_country" => $request->bill_country,
                ];
            } else {
                $shipping = [
                    "ship_first_name" => $request->bill_first_name,
                    "ship_last_name" => $request->bill_last_name,
                    "ship_email" => $request->bill_email,
                    "ship_phone" => $request->bill_phone,
                ];
            }

            Session::put('shipping_address', $shipping);

            // store shipping info into database
            if ($request->user()) {
                $user = $request->user();
                $user->ship_company = $request->bill_company;
                $user->ship_address1 = $request->bill_address1;
                $user->ship_address2 = $request->bill_address2;
                $user->ship_zip = $request->bill_zip;
                $user->ship_city = $request->bill_city;
                $user->ship_country = $request->bill_country;
                $user->save();
            }
        }

        if (Session::has('shipping_address')) {
            return redirect()->route('front.checkout.payment');
        } else {
            return redirect()->route('front.checkout.shipping');
        }
    }

    public function shipping()
    {
        if (Session::has('shipping_address')) {
            return redirect(route('front.checkout.payment'));
        }

        if (!Session::has('cart')) {
            return redirect(route('front.cart'));
        }
        $data['user'] = Auth::user();
        $cart = Session::get('cart');

        $total_tax = 0;
        $cart_total = 0;
        $total = 0;

        foreach ($cart as $key => $item) {
            $total += ($item['main_price'] + $item['attribute_price']) * $item['qty'];
            $cart_total = $total;

            $item = Item::findOrFail($key);
            if ($item->tax) {
                $total_tax += $item::taxCalculate($item);
            }
        }

        /*
        $shipping = [];
        if (ShippingService::whereStatus(1)->whereId(1)->whereIsCondition(1)->exists()) {
            $shipping = ShippingService::whereStatus(1)->whereId(1)->whereIsCondition(1)->first();
            if ($cart_total >= $shipping->minimum_price) {
                $shipping = $shipping;
            } else {
                $shipping = [];
            }
        }

        if (!$shipping) {
            $shipping = ShippingService::whereStatus(1)->where('id','!=',1)->first();
        }*/

        $discount = [];
        if (Session::has('coupon')) {
            $discount = Session::get('coupon');
        }

        if (!PriceHelper::Digital()) {
            $shipping = null;
        }

        /**
         * UPS API -> Get shipping rates
        */
        $shipping = null;
        if (Session::has('shipping_address')) {
            // creaee UPS Repository Object
            $upsObj = new USPSRepository();
            $code = Session::has('shippingServiceCode') ? Session::get('shippingServiceCode') : '1';
            Log::info('Code: '.$code);
            $data['shipping'] = $shipping = $upsObj->getShippingRate($cart, $code);
        }

        $grand_total = ($cart_total + ($shipping && $shipping['success'] ? $shipping['rate']['Postage']['Rate'] : 0)) + $total_tax;
        $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);
        $state_tax = Auth::check() && Auth::user()->state_id ? Auth::user()->state->price : 0;
        $grand_total = $grand_total + $state_tax;

        $total_amount = $grand_total;
        $data['cart'] = $cart;
        $data['cart_total'] = $cart_total;
        $data['grand_total'] = $total_amount;
        $data['discount'] = $discount;
        $data['shipping'] = $shipping;
        $data['tax'] = $total_tax;
        $data['payments'] = PaymentSetting::whereStatus(1)->get();
        return view('front.checkout.shipping',$data);
    }

    /**
     * Store shipping info
    */
    public function shippingStore(Request $request)
    {
        Session::put('shipping_address',$request->all());

        // store shipping info into database
        if ($request->user()) {
            $user = $request->user();
            $user->ship_company = $request->ship_company;
            $user->ship_address1 = $request->ship_address1;
            $user->ship_address2 = $request->ship_address2;
            $user->ship_zip = $request->ship_zip;
            $user->ship_city = $request->ship_city;
            $user->ship_country = $request->ship_country;
            $user->save();
        }

        return redirect(route('front.checkout.payment'));
    }

    public function payment()
    {
        if (!Session::has('billing_address')) {
            return redirect(route('front.checkout.billing'));
        }

        if (!Session::has('shipping_address')) {
            return redirect(route('front.checkout.shipping'));
        }

        if (!Session::has('cart')) {
            return redirect(route('front.cart'));
        }

        $data['user'] = Auth::user();
        $cart = Session::get('cart');

        $total_tax = 0;
        $cart_total = 0;
        $total = 0;

        foreach ($cart as $key => $item) {
            $total += ($item['main_price'] + $item['attribute_price']) * $item['qty'];
            $cart_total = $total;
            
            $item = Item::findOrFail($key);
            
            if ($item->tax) {
                $total_tax += $item::taxCalculate($item);
            }
        }

        /*
        $shipping = [];
        if (ShippingService::whereStatus(1)->whereId(1)->whereIsCondition(1)->exists()) {
            $shipping = ShippingService::whereStatus(1)->whereId(1)->whereIsCondition(1)->first();
            if($cart_total >= $shipping->minimum_price){
                $shipping = $shipping;
            }else{
                $shipping = [];
            }
        }

        if (!$shipping) {
            $shipping = ShippingService::whereStatus(1)->where('id','!=' , 1)->first();
        }*/

        $discount = [];
        if (Session::has('coupon')) {
            $discount = Session::get('coupon');
        }

        if (!PriceHelper::Digital()) {
            $shipping = null;
        }

        $shipping = null;
        /**
         * UPS API -> Get shipping rates
        */

        $uspsObj = new USPSRepository();
        $code = Session::has('shippingServiceCode') ? Session::get('shippingServiceCode') : '1';
        $data['shipping'] = $shipping = $uspsObj->getShippingRate($cart, $code);

        if ($shipping && $shipping['success']) {
            Session::put('shippingCost', $shipping['rate']['Postage']['Rate']);
        } elseif ($shipping && isset($shipping['message'])) {
            return redirect()->route('front.checkout.billing')->with('error', $shipping['message']);
        } else {
            return redirect()->route('front.checkout.billing')->with('error', 'Something went wrong, Please try agian later.');
        }

        $grand_total = $cart_total + $shipping['rate']['Postage']['Rate'] + $total_tax;
        $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);
        $state_tax = Auth::check() && Auth::user()->state_id ? Auth::user()->state->price : 0;
        $grand_total = $grand_total + $state_tax;

        $total_amount = $grand_total;

        $data['cart'] = $cart;
        $data['cart_total'] = $cart_total;
        $data['grand_total'] = $total_amount;
        $data['discount'] = $discount;
        $data['shipping'] = $shipping;
        $data['tax'] = $total_tax;
        $data['payments'] = PaymentSetting::whereStatus(1)->get();

        return view('front.checkout.payment', $data);
    }

	public function checkout(PaymentRequest $request)
	{
        $input = $request->all();

        $checkout = false;
        $payment_redirect = false;
        $payment = null;

        if (Session::has('currency')) {
            $currency = Currency::findOrFail(Session::get('currency'));
        } else {
            $currency = Currency::where('is_default',1)->first();
        }

        // use currency check
        $usd_supported = ['USD','EUR'];
        $paypal_supported = ['USD','EUR','AUD','BRL','CAD','HKD','JPY','MXN','NZD','PHP','GBP','RUB'];
        $paystack_supported = ['NGN'];

        switch ($input['payment_method']) {
            case 'Stripe':
                if (!in_array($currency->name,$usd_supported)) {
                    Session::flash('error',__('Currency Not Supported'));
                    return redirect()->back();
                }

                $checkout = true;
                $payment = $this->stripeSubmit($input);
                break;

            case 'Paypal':
                if (!in_array($currency->name, $paypal_supported)) {
                    Session::flash('error',__('Currency Not Supported'));
                    return redirect()->back();
                }

                $checkout = true;
                $payment_redirect = true;
                $payment = $this->paypalSubmit($input);
                break;

            case 'Mollie':
                if (!in_array($currency->name,$usd_supported)) {
                    Session::flash('error',__('Currency Not Supported'));
                    return redirect()->back();
                }

                $checkout = true;
                $payment_redirect = true;
                $payment = $this->MollieSubmit($input);
                break;

            case 'Paystack':
                if (!in_array($currency->name,$paystack_supported)) {
                    Session::flash('error',__('Currency Not Supported'));
                    return redirect()->back();
                }

                $checkout = true;
                $payment = $this->PaystackSubmit($input);
                break;

            case 'Bank':
                $checkout = true;
                $payment = $this->BankSubmit($input);
                break;

            case 'Cash On Delivery':
                $checkout = true;
                $payment = $this->cashOnDeliverySubmit($input);
                break;
        }

        if ($checkout) {
            if ($payment_redirect) {
                if ($payment['status']) {
                    return redirect()->away($payment['link']);
                } else {
                    Session::put('message', $payment['message']);
                    return redirect()->route('front.checkout.cancle');
                }
            } else {
                if ($payment['status']) {
                    return redirect()->route('front.checkout.success');
                } else {
                    Session::put('message',$payment['message']);
                    return redirect()->route('front.checkout.cancle');
                }
            }
        } else {
            return redirect()->route('front.checkout.cancle');
        }
	}

	public function paymentRedirect(Request $request)
	{
        $responseData = $request->all();
        if (Session::has('order_payment_id')) {
            $payment = $this->paypalNotify($responseData);

            if ($payment['status']) {
                return redirect()->route('front.checkout.success');
            } else {
                Session::put('message',$payment['message']);
                return redirect()->route('front.checkout.cancle');
            }
        } else {
            return redirect()->route('front.checkout.cancle');
        }
    }

	public function mollieRedirect(Request $request)
	{
        $responseData = $request->all();

        $payment = Mollie::api()->payments()->get(Session::get('payment_id'));
        $responseData['payment_id'] = $payment->id;

        if ($payment->status == 'paid') {
            $payment = $this->mollieNotify($responseData);
            if ($payment['status']) {
                return redirect()->route('front.checkout.success');
            } else {
                Session::put('message',$payment['message']);
                return redirect()->route('front.checkout.cancle');
            }
        } else {
            return redirect()->route('front.checkout.cancle');
        }
    }

	public function paymentSuccess()
	{
        if (Session::has('order_id')) {
            $order_id = Session::get('order_id');
            $order = Order::find($order_id);
            $cart = json_decode($order->cart, true);
            $setting = Setting::first();

            if ($setting->is_twilio == 1) {
                // message
                $sms = new SmsHelper();
                $user_number = $order->user->phone;

                if ($user_number) {
                    $sms->SendSms($user_number,"'purchase'");
                }
            }
            return view('front.checkout.success', compact('order','cart'));
        }
        return redirect()->route('front.index');
	}

	public function paymentCancle()
	{
        $message = '';
        if (Session::has('message')) {
            $message = Session::get('message');
            Session::forget('message');
        } else {
            $message = __('Payment Failed!');
        }

        Session::flash('error',$message);
        return redirect()->route('front.checkout.billing');
	}

    public function stateSetUp($state_id)
	{
        if (!Session::has('cart')) {
            return redirect(route('front.cart'));
        }

        $cart = Session::get('cart');
        $total_tax = 0;
        $cart_total = 0;
        $total = 0;

        foreach ($cart as $key => $item) {
            $total += ($item['main_price'] + $item['attribute_price']) * $item['qty'];
            $cart_total = $total;
            $item = Item::findOrFail($key);

            if ($item->tax) {
                $total_tax += $item::taxCalculate($item);
            }
        }

        /*$shipping = [];
        if(ShippingService::whereStatus(1)->whereId(1)->whereIsCondition(1)->exists()){
            $shipping = ShippingService::whereStatus(1)->whereId(1)->whereIsCondition(1)->first();
            if($cart_total >= $shipping->minimum_price){
                $shipping = $shipping;
            }else{
                $shipping = [];
            }
        }

        if(!$shipping){
            $shipping = ShippingService::whereStatus(1)->where('id','!=',1)->first();
        }*/

        $discount = [];
        if (Session::has('coupon')) {
            $discount = Session::get('coupon');
        }

        /**
         * UPS API -> Get shipping rates
        */
        $upsObj = new USPSRepository();
        $shippingServiceCode = Session::has('shippingServiceCode') ? Session::get('shippingServiceCode') : '03';
        $data['shipping'] = $shipping = $upsObj->getShippingRate($cart, $shippingServiceCode);
        
        $grand_total = ($cart_total + ($shipping && $shipping['success'] ? $shipping['rate']['Postage']['Rate'] : 0)) + $total_tax;
        $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);

        $state_price = 0;
        if ($state_id) {
            $state = State::findOrFail($state_id);
            if ($state->type == 'fixed') {
                $state_price = $state->price;
            } else {
                $state_price = ($cart_total * $state->price) / 100;
            }
        } else {
            if (Auth::check() && Auth::user()->state_id) {
                $state = Auth::user()->state;
                if ($state->type == 'fixed') {
                    $state_price = $state->price;
                } else {
                    $state_price = ($cart_total * $state->price) / 100;
                }
            } else {
                $state_price = 0;
            }
        }

        $total_amount = $grand_total + $state_price;

        $data['state_price'] = PriceHelper::setCurrencyPrice($state_price);
        $data['grand_total'] = PriceHelper::setCurrencyPrice($total_amount);

        return response()->json($data);
    }

    public function shippingSetup($code)
	{
        if (!Session::has('cart')) {
            return redirect(route('front.cart'));
        }

        $cart = Session::get('cart');
        $total_tax = 0;
        $cart_total = 0;
        $total = 0;

        foreach ($cart as $key => $item) {
            $total += ($item['main_price'] + $item['attribute_price']) * $item['qty'];
            $cart_total = $total;
            $item = Item::findOrFail($key);

            if ($item->tax) {
                $total_tax += $item::taxCalculate($item);
            }
        }

        $discount = [];
        if (Session::has('coupon')) {
            $discount = Session::get('coupon');
        }

        /**
         * UPS API -> Get shipping rates
        */
        $upsObj = new USPSRepository();
        Session::put('shippingServiceCode', $code);
        $data['shipping'] = $shipping = $upsObj->getShippingRate($cart, $code);
        
        $grand_total = ($cart_total + ($shipping && $shipping['success'] ? $shipping['rate']['Postage']['Rate'] : 0)) + $total_tax;
        $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);

        $data['shipping_price'] = $shipping && $shipping['success'] ? $shipping['rate']['Postage']['Rate'] : 0;
        $data['grand_total'] = PriceHelper::setCurrencyPrice($grand_total);

        return response()->json($data);
    }
}
