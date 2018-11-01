<?php

/**
 * Acquia/CommerceManager/Model/Cache/Type/Acm.php
 *
 * Acquia Commerce Manager custom Cache Type - ACM.
 *
 * All rights reserved. No unauthorized distribution or reproduction.
 */

namespace Acquia\CommerceManager\Model\Cache\Type;

/**
 * System / Cache Management / Cache type "ACM"
 */
class Acm extends \Magento\Framework\Cache\Frontend\Decorator\TagScope
{
    /**
     * Cache type code unique among all cache types.
     */
    const TYPE_IDENTIFIER = 'acm';

    /**
     * Cache tag used to distinguish the cache type from all other cache.
     */
    const CACHE_TAG = 'ACM';

    /**
     * @param \Magento\Framework\App\Cache\Type\FrontendPool $cacheFrontendPool
     */
    public function __construct(\Magento\Framework\App\Cache\Type\FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
