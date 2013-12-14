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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CompressionTestCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('cache:benchmark:comp_test')
            ->addOption('lib', null, InputOption::VALUE_OPTIONAL, 'One of this libs: snappy, lzf, gzip')
            ->setDescription('Test compression library performance');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if ($this->initMagento()) {

            $numChunks = 10240;
            $chunks = array();
            for ($i = 0; $i < $numChunks; $i++) {
                $chunks[] = '016_' . sha1(mt_rand(0, $numChunks * 10));
            }

            switch ($input->getOption('lib')) {
                case 'snappy':
                    $compress = 'snappy_compress';
                    $decompress = 'snappy_uncompress';
                    break;

                case 'lzf':
                    $compress = 'lzf_compress';
                    $decompress = 'lzf_decompress';
                    break;

                case 'gzip':
                    $compress = array($this, 'gzcompress');
                    $decompress = 'gzuncompress';
                    break;

                default:
                    throw new \RuntimeException("Please specify a lib with --lib. Options are: snappy, lzf, gzip");
            }
            if (!function_exists($decompress)) {
                echo "The function $decompress does not exist.\n";
                exit;
            }

            $output->writeln("Testing compression with <comment>{$input->getOption('lib')}</comment>");
            $len = 0;
            $zlen = 0;
            $compressTime = 0;
            $decompressTime = 0;
            for ($i = 0; $i < $numChunks; $i = ($i >= 1024 ? $i + 4096 : $i * 3)) {
                $string = implode(',', array_slice($chunks, 0, $i));
                $start = microtime(true);
                $zstring = call_user_func($compress, $string);
                $encode = microtime(true) - $start;
                $start = microtime(true);
                $ungztags = call_user_func($decompress, $zstring);
                $decode = microtime(true) - $start;
                $len += strlen($string);
                $zlen += strlen($zstring);
                $compressTime += $encode;
                $decompressTime += $decode;
                $output->writeln(sprintf("random from %d bytes to %d bytes (%2.4f%%) in %.6f seconds / %.6f seconds", strlen($string), strlen($zstring), ((strlen($string) - strlen($zstring)) / strlen($string)) * 100., $encode, $decode));
                if ($ungztags != $string) echo "ERROR\n";
                if (!$i) $i = 1;
            }
            foreach (array(
                         './app/code/core/Mage/Core/etc/system.xml',
                         './app/code/core/Mage/Catalog/etc/system.xml',
                         './app/code/core/Mage/Sales/etc/system.xml',
                         './app/code/core/Mage/Core/etc/config.xml',
                         './app/code/core/Mage/Catalog/etc/config.xml',
                         './app/code/core/Mage/Sales/etc/config.xml',
                         './app/design/frontend/base/default/template/catalog/product/view.phtml',
                         './app/design/frontend/base/default/template/catalog/product/list.phtml',
                         './app/design/frontend/base/default/template/page/3columns.phtml',
                     ) as $xmlFile) {
                $string = file_get_contents($xmlFile);
                $start = microtime(true);
                $zstring = call_user_func($compress, $string);
                $encode = microtime(true) - $start;
                $start = microtime(true);
                $ungztags = call_user_func($decompress, $zstring);
                $decode = microtime(true) - $start;
                $len += strlen($string);
                $zlen += strlen($zstring);
                $compressTime += $encode;
                $decompressTime += $decode;
                $output->writeln(sprintf("%s from %d bytes to %d bytes (%2.4f%%) in %.6f seconds / %.6f seconds", $xmlFile, strlen($string), strlen($zstring), ((strlen($string) - strlen($zstring)) / strlen($string)) * 100., $encode, $decode));
                if ($ungztags != $string) echo "ERROR\n";
            }
            foreach (array('\Mage::app()->getConfig()') as $xmlFile) {
                $string = \Mage::app()->getConfig()->getNode()->asXML();
                $start = microtime(true);
                $zstring = call_user_func($compress, $string);
                $encode = microtime(true) - $start;
                $start = microtime(true);
                $ungztags = call_user_func($decompress, $zstring);
                $decode = microtime(true) - $start;
                $len += strlen($string);
                $zlen += strlen($zstring);
                $compressTime += $encode;
                $decompressTime += $decode;
                $output->writeln(sprintf("%s from %d bytes to %d bytes (%2.4f%%) in %.6f seconds / %.6f seconds", $xmlFile, strlen($string), strlen($zstring), ((strlen($string) - strlen($zstring)) / strlen($string)) * 100., $encode, $decode));
                if ($ungztags != $string) {
                    $output->writeln("ERROR");
                }
            }
            $output->writeln(sprintf("<info>Total: from %d bytes to %d bytes (%2.4f%%) in %.6f seconds / %.6f seconds</info>", $len, $zlen, (($len - $zlen) / $len) * 100., $compressTime, $decompressTime));
        }
    }

    protected function gzcompress($data)
    {
        return gzcompress($data, 1);
    }
}