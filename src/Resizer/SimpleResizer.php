<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Resizer;

use Gaufrette\File;
use Imagine\Exception\InvalidArgumentException;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Sonata\MediaBundle\Metadata\MetadataBuilderInterface;
use Sonata\MediaBundle\Model\MediaInterface;

class SimpleResizer implements ResizerInterface
{
    /**
     * @var ImagineInterface
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var MetadataBuilderInterface
     */
    protected $metadata;

    /**
     * @param ImagineInterface         $adapter
     * @param string                   $mode
     * @param MetadataBuilderInterface $metadata
     */
    public function __construct(ImagineInterface $adapter, $mode, MetadataBuilderInterface $metadata)
    {
        $this->adapter = $adapter;
        $this->mode = $mode;
        $this->metadata = $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function resize(MediaInterface $media, File $in, File $out, $format, array $settings)
    {
        if (!isset($settings['width'])) {
            throw new \RuntimeException(sprintf('Width parameter is missing in context "%s" for provider "%s"', $media->getContext(), $media->getProviderName()));
        }

        $image = $this->adapter->load($in->getContent());

        $content = $image
            ->thumbnail($this->getBox($media, $settings), $this->mode)
            ->get($format, ['quality' => $settings['quality']]);

        $out->setContent($content, $this->metadata->get($media, $out->getName()));
    }

    /**
     * {@inheritdoc}
     */
    public function getBox(MediaInterface $media, array $settings)
    {
        $size = $media->getBox();

        if (null === $settings['width'] && null === $settings['height']) {
            throw new \RuntimeException(sprintf('Width/Height parameter is missing in context "%s" for provider "%s". Please add at least one parameter.', $media->getContext(), $media->getProviderName()));
        }

        if (null === $settings['height']) {
            $settings['height'] = (int) ($settings['width'] * $size->getHeight() / $size->getWidth());
        }

        if (null === $settings['width']) {
            $settings['width'] = (int) ($settings['height'] * $size->getWidth() / $size->getHeight());
        }

        return $this->computeBox($media, $settings);
    }

    /**
     * @param MediaInterface $media
     * @param array          $settings
     *
     * @throws InvalidArgumentException
     *
     * @return Box
     */
    protected function computeBox(MediaInterface $media, array $settings)
    {
        if (ImageInterface::THUMBNAIL_INSET !== $this->mode && ImageInterface::THUMBNAIL_OUTBOUND !== $this->mode) {
            throw new InvalidArgumentException('Invalid mode specified');
        }

        $size = $media->getBox();

        $ratios = [
            $settings['width'] / $size->getWidth(),
            $settings['height'] / $size->getHeight(),
        ];

        if (ImageInterface::THUMBNAIL_INSET === $this->mode) {
            $ratio = min($ratios);
        } else {
            $ratio = max($ratios);
        }

        return $size->scale($ratio);
    }
}
