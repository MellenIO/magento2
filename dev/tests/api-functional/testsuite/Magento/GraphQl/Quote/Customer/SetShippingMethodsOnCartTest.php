<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Quote\Customer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test for setting shipping methods on cart for customer
 */
class SetShippingMethodsOnCartTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var QuoteResource
     */
    private $quoteResource;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedId;

    /**
     * @var Rate
     */
    private $rate;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->quoteResource = $objectManager->get(QuoteResource::class);
        $this->quoteFactory = $objectManager->get(QuoteFactory::class);
        $this->quoteIdToMaskedId = $objectManager->get(QuoteIdToMaskedQuoteIdInterface::class);
        $this->quoteRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->rate = $objectManager->get(Rate::class);
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_virtual_product_and_address.php
     * @magentoApiDataFixture Magento/Checkout/_files/enable_all_shipping_methods.php
     * @throws \Exception
     */
    public function testShippingMethodWithVirtualProduct()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReversedQuoteId('test_order_with_virtual_product');

        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, 'test_order_with_virtual_product', 'reserved_order_id');

        $shippingAddress = $quote->getShippingAddress();
        $rate = $this->rate;

        $rate->setPrice(2)
            ->setAddressId($shippingAddress->getId())
            ->setCode('flatrate_flatrate');
        $shippingAddress->setShippingMethod('flatrate_flatrate')
            ->addShippingRate($rate)
            ->save();

        $mutation = $this->prepareMutationQuery(
            $maskedQuoteId,
            'flatrate',
            'flatrate_flatrate',
            $shippingAddress->getId()
        );

        $this->graphQlQuery($mutation, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoApiDataFixture Magento/Checkout/_files/enable_all_shipping_methods.php
     * @throws \Exception
     */
    public function testShippingMethodWithSimpleProduct()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReversedQuoteId('test_order_1');

        /** @var Product $product */
        $product = $this->productRepository->get('simple');

        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $quote->addProduct($product, 1);

        $shippingAddress = $quote->getShippingAddress();
        $rate = $this->rate;

        $rate->setPrice(2)
            ->setAddressId($shippingAddress->getId())
            ->setCode('flatrate_flatrate');
        $shippingAddress->setShippingMethod('flatrate_flatrate')
            ->addShippingRate($rate)
            ->save();

        $mutation = $this->prepareMutationQuery(
            $maskedQuoteId,
            'flatrate',
            'flatrate',
            $shippingAddress->getId()
        );

        $this->graphQlQuery($mutation, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_simple_product_saved.php
     * @magentoApiDataFixture Magento/Checkout/_files/enable_all_shipping_methods.php
     * @throws \Exception
     */
    public function testShippingMethodWithSimpleProductWithoutAddress()
    {
        $maskedQuoteId = $this->assignQuoteToCustomer('test_order_with_simple_product_without_address', 1);

        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, 'test_order_with_simple_product_without_address', 'reserved_order_id');

        $shippingAddress = $quote->getShippingAddress();
        $rate = $this->rate;

        $rate->setPrice(2)
            ->setAddressId($shippingAddress->getId())
            ->setCode('flatrate_flatrate');
        $shippingAddress->setShippingMethod('flatrate_flatrate')
            ->addShippingRate($rate)
            ->save();

        $mutation = $this->prepareMutationQuery(
            $maskedQuoteId,
            'flatrate',
            'flatrate',
            $shippingAddress->getId()
        );

        self::expectExceptionMessage(
            'The shipping address is missing. Set the address and try again.'
        );
        $this->graphQlQuery($mutation, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @throws \Exception
     */
    public function testSetShippingMethodWithMissedRequiredParameters()
    {
        $maskedQuoteId = $this->assignQuoteToCustomer('test_order_1', 1);

        $mutation = $this->prepareMutationQuery(
            $maskedQuoteId,
            '',
            '',
            '1'
        );

        self::expectExceptionMessage(
            'Required parameter "carrier_code" is missing.'
        );
        $this->graphQlQuery($mutation, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoApiDataFixture Magento/Checkout/_files/enable_all_shipping_methods.php
     * @throws \Exception
     */
    public function testSetNonExistentShippingMethod()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReversedQuoteId('test_order_1');

        /** @var Product $product */
        $product = $this->productRepository->get('simple');

        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $quote->addProduct($product, 1);

        $shippingAddress = $quote->getShippingAddress();
        $rate = $this->rate;

        $rate->setPrice(2)
            ->setAddressId($shippingAddress->getId())
            ->setCode('flatrate_flatrate');
        $shippingAddress->setShippingMethod('flatrate_flatrate')
            ->addShippingRate($rate)
            ->save();

        $mutation = $this->prepareMutationQuery(
            $maskedQuoteId,
            'non-existed-method-code',
            'non-existed-carrier-code',
            $shippingAddress->getId()
        );

        self::expectExceptionMessage(
            'Carrier with such method not found: non-existed-carrier-code, non-existed-method-code'
        );

        $this->graphQlQuery($mutation, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoApiDataFixture Magento/Customer/_files/customer_two_addresses.php
     * @magentoApiDataFixture Magento/Checkout/_files/enable_all_shipping_methods.php
     * @throws \Exception
     */
    public function testSetShippingMethodIfAddressIsNotBelongToCart()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReversedQuoteId('test_order_1');

        /** @var Product $product */
        $product = $this->productRepository->get('simple');

        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $quote->addProduct($product, 1);

        $shippingAddress = $quote->getShippingAddress();
        $rate = $this->rate;

        $rate->setPrice(2)
            ->setAddressId($shippingAddress->getId())
            ->setCode('flatrate_flatrate');
        $shippingAddress->setShippingMethod('flatrate_flatrate')
            ->addShippingRate($rate)
            ->save();

        $mutation = $this->prepareMutationQuery(
            $maskedQuoteId,
            'flatrate',
            'flatrate',
            '2'
        );

        self::expectExceptionMessage(
            'Could not find a cart address with ID "2"'
        );

        $this->graphQlQuery($mutation, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_address.php
     * @throws \Exception
     * @expectedException \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testSetShippingMethodToNonExistentCart()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReversedQuoteId('test_order_1');

        $mutation = $this->prepareMutationQuery(
            $maskedQuoteId,
            'flatrate',
            'flatrate',
            '1'
        );

        $this->graphQlQuery($mutation, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/Sales/_files/guest_quote_with_addresses.php
     * @magentoApiDataFixture Magento/Checkout/_files/enable_all_shipping_methods.php
     * @throws \Exception
     */
    public function testSetShippingMethodToGuestCart()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReversedQuoteId('guest_quote');

        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, 'guest_quote', 'reserved_order_id');

        $mutation = $this->prepareMutationQuery(
            $maskedQuoteId,
            'flatrate',
            'flatrate',
            $quote->getShippingAddress()->getId()
        );

        $this->graphQlQuery($mutation);
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_virtual_product_and_address.php
     * @throws \Exception
     */
    public function testSetShippingMethodToAnotherCustomerCart()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReversedQuoteId('test_order_with_virtual_product');

        /** @var Quote $quote test01 */
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $mutation = $this->prepareMutationQuery(
            $maskedQuoteId,
            'flatrate',
            'flatrate',
            $quote->getShippingAddress()->getId()
        );

        self::expectExceptionMessage(
            'Carrier with such method not found: flatrate, flatrate'
        );
        $this->graphQlQuery($mutation, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @throws \Exception
     */
    public function testSetShippingMethodToNonExistentCartAddress()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReversedQuoteId('test_order_1');

        $mutation = $this->prepareMutationQuery(
            $maskedQuoteId,
            'flatrate',
            'flatrate',
            '16800'
        );
        self::expectExceptionMessage(
            'Could not find a cart address with ID "16800"'
        );

        $this->graphQlQuery($mutation, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/Sales/_files/guest_quote_with_addresses.php
     * @magentoApiDataFixture Magento/Checkout/_files/enable_all_shipping_methods.php
     * @throws \Exception
     */
    public function testSetShippingMethodToGuestCartAddress()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReversedQuoteId('guest_quote');

        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, 'guest_quote', 'reserved_order_id');

        $mutation = $this->prepareMutationQuery(
            $maskedQuoteId,
            'flatrate',
            'flatrate',
            $quote->getShippingAddress()->getId()
        );

        $this->graphQlQuery($mutation);
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_virtual_product_and_address.php
     * @magentoApiDataFixture Magento/Checkout/_files/enable_all_shipping_methods.php
     * @throws \Exception
     */
    public function testSetShippingMethodToAnotherCustomerCartAddress()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReversedQuoteId('test_order_with_virtual_product');

        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $mutation = $this->prepareMutationQuery(
            $maskedQuoteId,
            'flatrate',
            'flatrate',
            $quote->getShippingAddress()->getId()
        );

        self::expectExceptionMessage(
            'Carrier with such method not found: flatrate, flatrate'
        );
        $this->graphQlQuery($mutation, [], '', $this->getHeaderMap());
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_shipping_method.php
     * @magentoApiDataFixture Magento/Checkout/_files/enable_all_shipping_methods.php
     * @throws \Exception
     */
    public function testSetMultipleShippingMethods()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReversedQuoteId('test_order_1');

        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $shippingAddressId = $quote->getShippingAddress()->getId();

        $mutation = <<<MUTATION
mutation {
  setShippingMethodsOnCart(input: 
    {
      cart_id: "$maskedQuoteId", 
      shipping_methods: [{
          cart_address_id: $shippingAddressId
          method_code: "flatrate"
          carrier_code: "flatrate"
        },
        {
          cart_address_id: $shippingAddressId
          method_code: "ups"
          carrier_code: "ups"
      }]
    }
    ) {
    cart {
      shipping_addresses {
        address_id
        firstname
        lastname
        selected_shipping_method {
          carrier_code
          method_code
          label
          amount
        }
      }
    }
  }
}

MUTATION;
        self::expectExceptionMessage(
            'You cannot specify multiple shipping methods.'
        );

        $this->graphQlQuery($mutation, [], '', $this->getHeaderMap());
    }

    /**
     * @param string $maskedQuoteId
     * @param string $shippingMethodCode
     * @param string $shippingCarrierCode
     * @param string $shippingAddressId
     * @return string
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function prepareMutationQuery(
        string $maskedQuoteId,
        string $shippingMethodCode,
        string $shippingCarrierCode,
        string $shippingAddressId
    ) : string {
        return <<<QUERY
mutation {
  setShippingMethodsOnCart(input: 
    {
      cart_id: "$maskedQuoteId", 
      shipping_methods: [{
        cart_address_id: $shippingAddressId
        method_code: "$shippingMethodCode"
        carrier_code: "$shippingCarrierCode"
      }]
    } ) 
    {
    cart {
      shipping_addresses {
        address_id
        firstname
        lastname
        selected_shipping_method {
          carrier_code
          method_code
          label
          amount
        }
      }
    }
  }
}

QUERY;
    }

    private function addShippingMethodToQuote(Quote $quote)
    {
    }

    /**
     * @param string $reversedOrderId
     * @return string
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function getMaskedQuoteIdByReservedOrderId(string $reversedOrderId): string
    {
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, $reversedOrderId, 'reserved_order_id');

        return $this->quoteIdToMaskedId->execute((int)$quote->getId());
    }

    /**
     * @param string $reversedOrderId
     * @param int $customerId
     * @return string
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function assignQuoteToCustomer(
        string $reversedOrderId,
        int $customerId
    ): string {
        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, $reversedOrderId, 'reserved_order_id');
        $quote->setCustomerId($customerId);
        $this->quoteResource->save($quote);
        return $this->quoteIdToMaskedId->execute((int)$quote->getId());
    }

    /**
     * @param string $username
     * @param string $password
     * @return array
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function getHeaderMap(string $username = 'customer@example.com', string $password = 'password'): array
    {
        $customerToken = $this->customerTokenService->createCustomerAccessToken($username, $password);
        $headerMap = ['Authorization' => 'Bearer ' . $customerToken];
        return $headerMap;
    }
}
