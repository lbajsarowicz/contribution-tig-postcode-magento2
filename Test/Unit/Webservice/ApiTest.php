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
namespace TIG\Postcode\Test\Unit\Webservice;

use Laminas\Http\Client;
use Laminas\Http\Client\Exception\RuntimeException;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Magento\Framework\HTTP\PhpEnvironment\ServerAddress;
use TIG\Postcode\Config\Provider\ApiConfiguration;
use TIG\Postcode\Config\Provider\ClientConfiguration;
use TIG\Postcode\Services\Converter\Factory;
use TIG\Postcode\Test\TestCase;
use TIG\Postcode\Webservices\Api;
use TIG\Postcode\Webservices\Endpoints\EndpointInterface;

class ApiTest extends TestCase
{
    protected $instanceClass = Api::class;

    public static function requestProvider()
    {
        return [
            'NL v3 GET' => ['NL', 'v3', Request::METHOD_GET],
            'NL v6 GET' => ['NL', 'v6', Request::METHOD_GET],
            'NL v6 POST' => ['NL', 'v6', Request::METHOD_POST],
            'BE GET' => ['BE', 'v3', Request::METHOD_GET],
            'DE GET' => ['DE', 'v3', Request::METHOD_GET],
            'FR GET' => ['FR', 'v3', Request::METHOD_GET],
        ];
    }

    /** @dataProvider requestProvider */
    #[\PHPUnit\Framework\Attributes\DataProvider('requestProvider')]
    public function testRequest($country, $version, $method)
    {
        $client = $this->createMock(Client::class);
        $apiConfiguration = $this->createMock(ApiConfiguration::class);
        $clientConfiguration = $this->createMock(ClientConfiguration::class);
        $converter = $this->createMock(Factory::class);
        $endpoint = $this->createMock(EndpointInterface::class);
        $serverAddress = $this->createMock(ServerAddress::class);
        $data = ['zipcode' => '1014BA', 'house_number' => '37'];
        $endpoint->method('getCountry')->willReturn($country);
        $endpoint->method('getEndpoint')->willReturn('address-validation/');
        $endpoint->method('getMethod')->willReturn($method);
        $endpoint->method('getResponseKeys')->willReturn(['results', 'error_code']);
        $apiConfiguration->method('getVersion')->willReturn($version);
        $baseMethod = $country === 'NL' ? 'getBaseUri' : 'get' . $country . 'BaseUri';
        $apiConfiguration->expects($this->once())->method($baseMethod)->willReturn('https://example.com/');
        $client->expects($this->once())->method('resetParameters');
        $client->expects($this->once())->method('setUri')->with('https://example.com/address-validation/');
        $client->expects($this->once())->method('setMethod')->with($method);
        $clientConfiguration->expects($this->once())->method('getClientId')->willReturn('11111');
        $clientConfiguration->expects($this->once())->method('getSecureCode')->willReturn('APIKEY');
        $clientConfiguration->expects($this->once())->method('getDomainUrl')->willReturn('example.com');
        if ($country === 'NL' && $version === 'v3') {
            $withCredentials = $data + ['client_id' => '11111', 'secure_code' => 'APIKEY'];
            $endpoint->expects($this->once())->method('setRequestData')->with($withCredentials);
            $endpoint->method('getRequestData')->willReturnOnConsecutiveCalls($data, $withCredentials);
            $data = $withCredentials;
            $client->expects($this->never())->method('setHeaders');
        } else {
            $endpoint->method('getRequestData')->willReturn($data);
            $client->expects($this->once())->method('setOptions')->with(['strict' => false]);
            $client->expects($this->once())->method('setHeaders')->with([
                'X-ClientId' => '11111', 'X-SecureCode' => 'APIKEY'
            ]);
        }
        $parameterMethod = $method === Request::METHOD_GET ? 'setParameterGet' : 'setParameterPost';
        $client->expects($this->once())->method($parameterMethod)->with($data + ['domain' => 'example.com']);
        $response = new Response();
        $response->setContent('{}');
        $client->expects($this->once())->method('send')->willReturn($response);
        $converter->expects($this->once())->method('convert')
            ->with('response', '{}', ['results', 'error_code'])->willReturn(['results' => []]);
        $instance = new Api($client, $apiConfiguration, $clientConfiguration, $converter, $serverAddress);
        $this->assertSame(['results' => []], $instance->getRequest($endpoint));
    }

    public function testApiWithHttpClientException()
    {
        $client = $this->createMock(Client::class);
        $apiConfiguration = $this->createMock(ApiConfiguration::class);
        $apiConfiguration->method('getVersion')->willReturn('v6');
        $endpoint = $this->createMock(EndpointInterface::class);
        $endpoint->method('getCountry')->willReturn('NL');
        $endpoint->method('getMethod')->willReturn(Request::METHOD_GET);
        $endpoint->method('getRequestData')->willReturn([]);
        $client->expects($this->once())->method('send')->willThrowException(new RuntimeException('Failed', 500));
        $instance = new Api(
            $client,
            $apiConfiguration,
            $this->createMock(ClientConfiguration::class),
            $this->createMock(Factory::class),
            $this->createMock(ServerAddress::class)
        );
        $response = $instance->getRequest($endpoint);
        $this->assertFalse($response['success']);
        $this->assertSame('500 : Laminas Http Client exception', (string) $response['error']);
    }
}
