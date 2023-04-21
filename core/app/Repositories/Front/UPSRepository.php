<?php

namespace App\Repositories\Front;

use App\Models\Box;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Log;

class UPSRepository
{
    /**
     * Validate shipping address
    */
    public function validateAddress()
    {
        $shipping = Session::get('shipping_address');

        $address = new \Ups\Entity\Address();
        $address->setAttentionName($shipping['ship_first_name'].' '.$shipping['ship_last_name']);
        $address->setAddressLine1($shipping['ship_address1']);

        if (isset($shipping['ship_address2'])) {
            $address->setAddressLine2($shipping['ship_address2']);
        }
        
        //$address->setStateProvinceCode($shipping['']);
        $address->setCity($shipping['ship_city']);
        //$address->setCountryCode($shipping['']);
        $address->setPostalCode($shipping['ship_zip']);

        $xav = new \Ups\AddressValidation(config('ups.access_key'), config('ups.user_id'), config('ups.password'));
        $xav->activateReturnObjectOnValidate(); //This is optional

        try {
            $response = $xav->validate($address, $requestOption = \Ups\AddressValidation::REQUEST_OPTION_ADDRESS_VALIDATION, $maxSuggestion = 15);
            Log::info(print_r($response, true));
        } catch (\Exception $e) {
            Log::info('Exception: '. $e->getMessage());
        }
    }

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

    private function getBoxDimentions($cart)
    {
        try {
            $str = '';
            foreach ($cart as $key => $item) {
                $names = json_decode($item['specification_name'], true);
                $values = json_decode($item['specification_description'], true);
                $newArr = array_combine($names, $values);
                Log::info(print_r($newArr, true));
                $str .= $newArr['Dimensions (h,w,l):'].'|';

            }
            // remove last pipe
            $str = rtrim($str, "|");
            
            // get all items dimensions
            $dimensions = explode("|", $str);

            /**
             * 1. Find total volume
            */
            $volume = 0;

            /**
             * 2. Find WHD ranges
            */
            $widthRange = $heightRange = $depthRange = [];

            foreach ($dimensions as $dimension) {
                list($height, $width, $depth) = explode(',', $dimension);
                $volume += $width * $height * $depth;
                $widthRange[] = $width;
                $heightRange[] = $height;
                $depthRange[] = $depth;
            }

            /**
             * 3. Order the WHD ranges
            */
            sort($widthRange);
            sort($heightRange);
            sort($depthRange);

            /**
             * 4. Figure out every combination with WHD
            */
            $widthCombination = $heightCombination = $depthCombination = [];

            function combination($list) {
                $combination = [];
                $total = pow(2, count($list));

                for ($i = 0; $i < $total; $i++) {   
                    $set = [];

                    //For each combination check if each bit is set  
                    for ($j = 0; $j < $total; $j++) {  
                        //Is bit $j set in $i?  
                        if (pow(2, $j) & $i) $set[] = $list[$j];       
                    }

                    if (empty($set) || in_array(array_sum($set), $combination)) {
                        continue;
                    }

                    $combination[] = array_sum($set);
                }

                sort($combination);
                return $combination;
            }

            $widthCombination = combination($widthRange);
            $heightCombination = combination($heightRange);
            $depthCombination = combination($depthRange);

            $stacks = [];
            foreach ($widthCombination as $width) {
                foreach ($heightCombination as $height) {
                    foreach ($depthCombination as $depth) {
                        $v = $width * $height * $depth;
                        if ($v >= $volume) {
                            $stacks[$v][$width+$height+$depth] = array($width, $height, $depth);
                        }
                    }
                }
            }

            ksort($stacks);

            foreach ($stacks as $i => $dims) {
                ksort($stacks[$i]);
                
                foreach ($stacks[$i] as $j => $stack) {
                    rsort($stack);
                    break;
                }
                break;
            }

            // return the first possible box size
            $first = Arr::first(Arr::first($stacks));
            return [
                'success' => true,
                'dimension' => $first,
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
    public function getShippingRate($cart, $shippingCode = '03')
    {
        $rate = new \Ups\Rate(
            config('ups.access_key'),
            config('ups.user_id'),
            config('ups.password')
        );

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

            Session::put('cartTotalWeight', $itemsWeight);

            $boxDimensions = $this->getBoxDimentions($cart);
            if (!$boxDimensions['success']) {
                return [
                    'success' => false,
                    'message' => $boxDimensions['message'],
                ];
            }

            $boxDimensions = $boxDimensions['dimension'];
            Session::put('boxDimensions', $boxDimensions);

            $shipment = new \Ups\Entity\Shipment();

            $service = new \Ups\Entity\Service;

            switch($shippingCode) {
                case '02':
                    $service->setCode(\Ups\Entity\Service::S_AIR_2DAY);
                    break;
                
                case '03':
                    $service->setCode(\Ups\Entity\Service::S_GROUND);
                    break;
                
                case '12':
                    $service->setCode(\Ups\Entity\Service::S_3DAYSELECT);
                    break;
                
                case '13':
                    $service->setCode(\Ups\Entity\Service::S_AIR_1DAYSAVER);
                    break;
                default:
                    return [
                        'success' => false,
                        'message' => 'No shipping service matched',
                    ];
                    break;
            }
            
            $service->setDescription($service->getName());
            $shipment->setService($service);

            $shipperAddress = $shipment->getShipper()->getAddress();
            $shipperAddress->setPostalCode('99205');

            // Set ship from address
            $address = new \Ups\Entity\Address();
            $address->setAddressLine1('1659 HWY 20 WEST');
            $address->setCity('MCDONOUGH');
            $address->setPostalCode('30253');
            $address->setStateProvinceCode('GA');
            $address->setCountryCode('US');

            $shipFrom = new \Ups\Entity\ShipFrom();
            $shipFrom->setAddress($address);

            $shipment->setShipFrom($shipFrom);

            // Set ship to address
            $shipTo = $shipment->getShipTo();
            $shipTo->setCompanyName($shippingAddress['ship_company']);
            $shipTo->setAttentionName($shippingAddress['ship_first_name'].' '.$shippingAddress['ship_last_name']);
            $shipTo->setEmailAddress($shippingAddress['ship_email']);
            $shipTo->setPhoneNumber($shippingAddress['ship_phone']);
            $shipToAddress = $shipTo->getAddress();
            $shipToAddress->setPostalCode($shippingAddress['ship_zip']);
            $shipToAddress->setAddressLine1($shippingAddress['ship_address1']);
            
            // If shipping address line 2 is set
            if (isset($shippingAddress['ship_address2'])) {
                $shipToAddress->setAddressLine2($shippingAddress['ship_address2']);
            }

            $package = new \Ups\Entity\Package();
            $package->getPackagingType()->setCode(\Ups\Entity\PackagingType::PT_PACKAGE );
            //$package->getPackagingType()->setCode($shippingCode);
            $package->getPackageWeight()->setWeight($itemsWeight);
            
            // if you need this (depends of the shipper country)
            $weightUnit = new \Ups\Entity\UnitOfMeasurement;
            $weightUnit->setCode(\Ups\Entity\UnitOfMeasurement::UOM_LBS);
            $package->getPackageWeight()->setUnitOfMeasurement($weightUnit);

            /*
            $dimensions = new \Ups\Entity\Dimensions();
            $dimensions->setHeight($boxDimensions[1]);
            $dimensions->setWidth($boxDimensions[0]);
            $dimensions->setLength($boxDimensions[2]);

            $unit = new \Ups\Entity\UnitOfMeasurement;
            $unit->setCode(\Ups\Entity\UnitOfMeasurement::UOM_IN);

            $dimensions->setUnitOfMeasurement($unit);
            $package->setDimensions($dimensions);*/

            $shipment->addPackage($package);
        
            //var_dump($rate->getRate($shipment));
            
            $rate = $rate->getRate($shipment);
            //Log::info(print_r($rate, true));
            
            $shipping = [
                'rate' => $rate,
                'weight' => $itemsWeight,
                'dimensions' => $boxDimensions,
                'service' => $shippingCode,
            ];
            Session::put('shipping', $shipping);

            return [
                'success' => true,
                'rate' => $rate->RatedShipment[0],
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
