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
namespace TIG\Postcode\Test\Unit\Plugin\Address;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Stdlib\ArrayManager;
use TIG\Postcode\Plugin\Model\Checkout\LayoutProcessorPlugin;
use TIG\Postcode\Test\TestCase;

class LayoutProcessorTest extends TestCase
{
    protected $instanceClass = LayoutProcessorPlugin::class;

    public function testAfterProcess()
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->never())->method('addMajor');
        $instance = new LayoutProcessorPlugin(
            new ArrayManager(),
            $notifier,
            $this->createMock(ScopeConfigInterface::class)
        );
        $layout = ['shippingAddress' => ['children' => [
            'postcode' => [
                'component' => 'Magento_Ui/js/form/element/post-code',
                'dataScope' => 'shippingAddress.postcode'
            ],
            'street' => [],
            'city' => []
        ]]];
        $result = $instance->afterProcess(null, $layout);
        $fields = $result['shippingAddress']['children'];
        $this->assertSame('TIG_Postcode/js/form/element/tig-postcode-field', $fields['postcode']['component']);
        $this->assertSame('TIG_Postcode/form/element/postcode-field', $fields['postcode']['config']['elementTmpl']);
        $this->assertSame(50, $fields['postcode']['config']['sortOrder']);
        foreach (['street', 'city', 'postcode'] as $field) {
            $this->assertTrue($fields[$field]['tracks']['additionalClasses']);
        }
        $this->assertSame('shippingAddress.custom_attributes.tig_housenumber', $fields['tig_housenumber']['dataScope']);
        $this->assertSame(51, $fields['tig_housenumber']['sortOrder']);
        $this->assertArrayHasKey('tig_housenumber_addition', $fields);
        $this->assertArrayHasKey('tig_street', $fields);
    }

    public function testIncompatibleFieldIsReported()
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())->method('addMajor')->with(
            'TIG Postcode',
            'Postcodeservice detected one or more compatibility issues with your shop setup'
        );
        $instance = new LayoutProcessorPlugin(
            new ArrayManager(),
            $notifier,
            $this->createMock(ScopeConfigInterface::class)
        );
        $layout = ['postcode' => ['component' => 'custom/postcode']];
        $this->assertSame($layout, $instance->afterProcess(null, $layout));
    }
}
