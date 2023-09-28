<?php

namespace MDC\Abandoncart\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;

class SendQuoteEmailCommand extends Command
{
    protected $transportBuilder;
    protected $appState;
    protected $storeManager;
    protected $scopeConfig;
    protected $cart;
    protected $quoteCollectionFactory;
    protected $quoteRepository;
    protected $senderResolver;
    protected $customerRepository;

    //const XML_PATH_ABANDONED_EMAIL = 'Abandoncart/Abandoncart/email_template';

    public function __construct(
        TransportBuilder $transportBuilder,
        State $appState,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $quoteCollectionFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        SenderResolverInterface $senderResolver,
        CustomerRepositoryInterface $customerRepository
    ) {
        parent::__construct();
        $this->transportBuilder = $transportBuilder;
        $this->appState = $appState;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->quoteRepository = $quoteRepository;
        $this->senderResolver = $senderResolver;
        $this->customerRepository = $customerRepository;
        
    }

    protected function configure()
    {
        $this->setName('mdcabandoncart:sendquoteemail')
            ->setDescription('Send quote details via email.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $quoteCollection = $this->quoteCollectionFactory->create();
        $quoteIds = $quoteCollection->getAllIds();
        if (!empty($quoteIds)) {
            foreach ($quoteIds as $quoteId) {
                // Load the quote by ID
                list($quote, $customer) = $this->loadQuoteById($quoteId);
                // Check if the quote exists
                if ($quote) {
                    // Get quote items
                    $quoteItems = $quote->getAllVisibleItems();
                    foreach ($quoteItems as $quoteItem) {
                        // Access item information
                        $productName = $quoteItem->getProduct()->getName();
                        $productPrice = $quoteItem->getProduct()->getFinalPrice();
                        // Send the email with quote details to the customer
                        if ($customer) {
                            $this->sendEmail($quoteId, $productName, $productPrice, $customer);
                        }
                    }
                } else {
                    $output->writeln("Quote with ID " . $quoteId . " not found.");
                }
            }
        } else {
            $output->writeln("No quote IDs found.");
        }
    }

    /**
     * Load a quote by its ID.
     *
     * @param int $quoteId
     * @return \Magento\Quote\Model\Quote|false
     */
    protected function loadQuoteById($quoteId)
    {
        try {
            $quote = $this->quoteRepository->get($quoteId);
            $customerId = $quote->getCustomerId();
            if ($customerId) {
                $customer = $this->customerRepository->getById($customerId);
                return [$quote, $customer];
            }
            return [$quote, null]; // Return the quote and null for customer if no customer is associated.
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return [null, null]; // Return null for both quote and customer if the quote doesn't exist.
        }
    }  
    protected function sendEmail($quoteId, $productName, $productPrice, $customer)
    {
        $recipientEmail = $customer->getEmail();
        
        // Load your email template using Magento's email template model
        $templateId = $this->scopeConfig->getValue('Abandoncart/Abandoncart/email_template'); 

        // Set the template variables
        $templateVars = [
            'quote_id' => $quoteId,
            'product_name' => $productName,
            'product_price' => $productPrice,
        ];

        // Set the area code to FRONTEND
        $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);


        // Create the email transport
        $this->transportBuilder->setTemplateIdentifier($templateId)
        ->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $this->storeManager->getStore()->getId()])
        ->setTemplateVars($templateVars)
        ->addTo($recipientEmail)
        ->getTransport()
        ->sendMessage();
    }  
}
