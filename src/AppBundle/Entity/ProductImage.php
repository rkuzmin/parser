<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProductImage
 *
 * @ORM\Table(name="product_image")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ProductImageRepository")
 */
class ProductImage
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="product_id", type="integer")
     */
    private $productId;


    /**
     * @var string
     *
     * @ORM\Column(name="big_url", type="string", length=255)
     */
    private $bigUrl;

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set productId
     *
     * @param integer $productId
     *
     * @return ProductImage
     */
    public function setProductId($productId)
    {
        $this->productId = $productId;

        return $this;
    }

    /**
     * Get productId
     *
     * @return int
     */
    public function getProductId()
    {
        return $this->productId;
    }

    /**
     * Set big url
     *
     * @param string $bigUrl
     *
     * @return ProductImage
     */
    public function setBigUrl($bigUrl)
    {
        $this->bigUrl = $bigUrl;

        return $this;
    }

    /**
     * Get big url
     *
     * @return string
     */
    public function getBigUrl()
    {
        return $this->bigUrl;
    }

}
