<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @copyright  Copyright (c) 2011 Vinai Kopp http://netzarbeiter.com, Colin Mollenhour http://colin.mollenhour.com,
 *             Christian Münch http://blog.muench-worms.de
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @author     Vinai Kopp http://netzarbeiter.com
 * @author     Colin Mollenhour http://colin.mollenhour.com
 * @author     Christian Münch http://blog.muench-worms.de
 */

namespace CM\Magerun\CacheBenchmark\Command;

use N98\Magento\Command\AbstractMagentoCommand;

abstract class AbstractCommand extends AbstractMagentoCommand
{
    /**
     * @var array $_tags Array of tags to benchmark
     */
    protected $_tags = array();

    /**
     * @var int $_maxTagLength The length of the longest cache tag
     * @see benchmark::_getLongestTagLength()
     */
    protected $_maxTagLength = 0;

    /**
     * @var string $_cachePrefix The cache tag and id prefix for this Magento instance
     * @see benchmark::_getCachePrefix()
     */
    protected $_cachePrefix = '';

    /**
     * @param string $name
     * @return string
     */
    protected function _getTestDir($name)
    {
        $baseDir = \Mage::getBaseDir('var') . '/cachebench';
        if (!is_dir($baseDir)) {
            mkdir($baseDir);
        }
        return $baseDir . '/' . $name;
    }

    /**
     * Get random data for cache
     *
     * @param $min
     * @param $max
     * @return string
     */
    protected function _getRandomData($min, $max = NULL)
    {
        if ($max === NULL) {
            $length = $min;
        } else {
            $length = mt_rand($min, $max);
        }
        $string = md5(mt_rand());
        while (strlen($string) < $length) {
            $string = base64_encode($string);
        }
        return substr($string, 0, $length);
    }

    /**
     * Return the length of the longest tag in the $_tags property.
     *
     * @param bool $force If true don't use the cached value
     * @return int The length of the longest tag
     */
    protected function _getLongestTagLength($force = false)
    {
        if (0 === $this->_maxTagLength || $force) {
            $len = 0;
            foreach ($this->_tags as $tag) {
                $tagLen = strlen($tag);
                if ($tagLen > $len) {
                    $len = $tagLen;
                }
            }
            $this->_maxTagLength = $len;
        }
        return $this->_maxTagLength;
    }

}