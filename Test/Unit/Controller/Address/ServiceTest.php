<?php
/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@postcodeservice.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@postcodeservice.com for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Postcode\Test\Unit\Controller\Address;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use TIG\Postcode\Controller\Address\Service;
use TIG\Postcode\Services\Converter\Factory;
use TIG\Postcode\Test\TestCase;
use TIG\Postcode\Webservices\Endpoints;

class ServiceTest extends TestCase
{
    public static function requestProvider()
    {
        return [
            'NL success' => ['nl', 'postcodecheck', true, true],
            'BE postcode' => ['be', 'getpostcode', true, true],
            'BE street' => ['be', 'getstreet', true, true],
            'DE postcode' => ['de', 'getpostcode', true, true],
            'DE street' => ['de', 'getstreet', true, true],
            'FR postcode' => ['fr', 'getpostcode', true, true],
            'FR street' => ['fr', 'getstreet', true, true],
            'invalid request' => ['nl', 'postcodecheck', false, true],
            'invalid response' => ['nl', 'postcodecheck', true, false]
        ];
    }

    /** @dataProvider requestProvider */
    #[\PHPUnit\Framework\Attributes\DataProvider('requestProvider')]
    public function testExecute($country, $method, $validRequest, $validResponse)
    {
        $params = [$country => $method, 'zipcode' => '1014BA', 'house_number' => '37'];
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParams')->willReturn($params);
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $classes = [
            'nl' => ['postcodecheck' => Endpoints\GetNLAddressValidation::class],
            'be' => ['getpostcode' => Endpoints\GetBEZipcodeFind::class, 'getstreet' => Endpoints\GetBEStreetFind::class],
            'de' => ['getpostcode' => Endpoints\GetDEZipcodeFind::class, 'getstreet' => Endpoints\GetDEStreetFind::class],
            'fr' => ['getpostcode' => Endpoints\GetFRZipcodeFind::class, 'getstreet' => Endpoints\GetFRStreetFind::class]
        ];
        $endpoints = [];
        $result = ['results' => [['street' => 'Kabelweg']]];
        foreach ($classes as $endpointCountry => $methods) {
            foreach ($methods as $endpointMethod => $class) {
                $endpoint = $this->createMock($class);
                $selected = $endpointCountry === $country && $endpointMethod === $method;
                if ($selected) {
                    $endpoint->expects($this->once())->method('getRequestKeys')->willReturn(['zipcode', 'house_number']);
                }
                $endpoint->expects($selected && $validRequest ? $this->once() : $this->never())
                    ->method('setRequestData')->with($params);
                $endpoint->expects($selected && $validRequest ? $this->once() : $this->never())
                    ->method('call')->willReturn($validResponse ? $result : false);
                $endpoints[] = $endpoint;
            }
        }
        $converter = $this->createMock(Factory::class);
        $converter->expects($this->once())->method('convert')
            ->with('request', $params, ['zipcode', 'house_number'])->willReturn($validRequest ? $params : false);
        $json = $this->createMock(Json::class);
        $expected = $result;
        if (!$validRequest || !$validResponse) {
            $expected = [
                'success' => false,
                'error' => $validRequest ? 'Response keys validation failed' : 'Request keys validation failed'
            ];
        }
        $json->expects($this->once())->method('setData')->with($expected)->willReturnSelf();
        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->expects($this->once())->method('create')->willReturn($json);
        $instance = new Service($context, $jsonFactory, $converter, ...$endpoints);
        $this->assertSame($json, $instance->execute());
    }
}
