<?php
namespace MDC\Abandoncart\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Email\Model\ResourceModel\Template\CollectionFactory;

class EmailTemplate implements ArrayInterface
{
    protected $templateCollectionFactory;

    public function __construct(
        CollectionFactory $templateCollectionFactory
    ) {
        $this->templateCollectionFactory = $templateCollectionFactory;
    }

    public function toOptionArray()
    {
        $templateCollection = $this->templateCollectionFactory->create();
        $options = [];
        foreach ($templateCollection as $template) {
            $options[] = [
                'value' => $template->getId(),
                'label' => $template->getTemplateCode(),
            ];
        }
        return $options;
    }
}
