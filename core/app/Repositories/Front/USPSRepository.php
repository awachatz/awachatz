<?php

namespace App\Repositories\Front;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use USPS\RatePackage;
use Log;

class USPSRepository
{
    private function getItemsWeight($cart)
    {
        try {
            $weight = 0;
            foreach ($cart as $key => $item) {
                $names = json_decode($item['specification_name'], true);
                $values = json_decode($item['specification_description'], true);
                $index = array_search('Weight (lbs):', $names);
                $weight += $values[$index];
            }
            //Log::info('W: '.$weight);
            return [
                'success' => true,
                'weight' => $weight,
            ];
        } catch (\Exception $e) {
            Log::emergency($e->getMessage.' at line # '.$e->getLine()." ".$e->getFile());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get shipping rates
     * based on shipping address
     * and products weight & package dimensions
    */
    public function getShippingRate($cart, $code = '1')
    {
        try {
            $user = Auth::user();
            $shippingAddress = Session::get('shipping_address');
            $itemsWeight = $this->getItemsWeight($cart);
            if (!$itemsWeight['success']) {
                return [
                    'success' => false,
                    'message' => $itemsWeight['message'],
                ];
            }

            $itemsWeight = $itemsWeight['weight'];

            // Save items weight
            Session::put('cartTotalWeight', $itemsWeight);

            // Set USPS user id
            $rate = new \USPS\Rate('824AWACH7401');

            $reqService = 'PARCEL SELECT GROUND';
            if ($code == '2') {
                $reqService = 'Priority Mail';
            }

            $package = new RatePackage();
            $package->setService($reqService);
            $package->setFirstClassMailType(RatePackage::MAIL_TYPE_PACKAGE);
            $package->setZipOrigination(30253);
            $package->setZipDestination($shippingAddress['ship_zip']); //30008
            $package->setPounds(0);
            $package->setOunces($itemsWeight);
            $package->setContainer('');
            $package->setSize(RatePackage::SIZE_REGULAR);
            $package->setField('Machinable', true);

            // add the package to the rate stack
            $rate->addPackage($package);
            $rate->getRate();
            $rate = $rate->getArrayResponse();

            Session::put('shipping', $rate);

            Log::info(print_r($rate, true));
            $rate = $rate['RateV4Response']['Package'];

            if (isset($rate['Error'])) {
                return [
                    'success' => false,
                    'message' => $rate['Error']['Description'],
                ];
            }

            return [
                'success' => true,
                'rate' => $rate,
            ];
        } catch (\Exception $e) {
            Log::info('Excpetion');
            Log::info($e->getMessage(). 'at line# '.$e->getLine());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

}
