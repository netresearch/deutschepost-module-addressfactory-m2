<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\ViewModel\Adminhtml;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use PostDirekt\Addressfactory\Model\AnalysisResult;
use PostDirekt\Addressfactory\Model\AnalysisResultRepository;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * AnalysisData
 *
 * @author  Gurjit Singh <gurjit.singh@netresearch.de>
 * @link    https://www.netresearch.de/
 */
class AnalysisData implements ArgumentInterface
{
    /**
     * @var AnalysisResultRepository
     */
    private $analysisResultRepository;

    /**
     * @var AssetRepository
     */
    private $assetRepository;

    public function __construct(
        AnalysisResultRepository $analysisResultRepository,
        AssetRepository $assetRepository
    ) {
        $this->analysisResultRepository = $analysisResultRepository;
        $this->assetRepository = $assetRepository;
    }

    public function getAnalysisResult(int $addressId): ?AnalysisResult
    {
        try {
            return $this->analysisResultRepository->getByAddressId($addressId);
        } catch (NoSuchEntityException $exception) {
            return null;
        }
    }

    public function getLogoUrl(): string
    {
        return $this->assetRepository->getUrl('PostDirekt_Addressfactory::images/logo_addressfactory.png');
    }
}
