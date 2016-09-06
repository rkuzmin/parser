<?php
namespace AppBundle\Command;

use AppBundle\Entity\Product;
use AppBundle\Entity\ProductImage;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

class ParseCommand extends ContainerAwareCommand
{
    const SITEMAP_URL = 'https://www.hunkemoller.com/en/catalog/seo_sitemap/product/';
    const FOLDER = './images/';

    protected function configure()
    {
        $this
            ->setName('app:parse')
            ->setDescription('Parse')
            ->setHelp("This command allows you to parse site...");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
//        $this->parseProductsUrls($output);

//        $this->parseProductsInfo($output);

//        $this->parseImages($output);

        $this->parseBigImages($output);

        $output->writeln('exit');
    }

    /**
     * Парсинг ссылок на все продукты
     * @param OutputInterface $output
     */
    protected function parseProductsUrls(OutputInterface $output)
    {
        $lastPage = $this->parseLastPage();
        $output->writeln('Кол-во страниц товаров: ' . $lastPage);
        for ($page = 1; $page <= $lastPage; $page++) {
            $output->writeln('Страница №' . $page);
            $crawler = new Crawler();
            $content = $this->getContent(static::SITEMAP_URL . '?p=' . $page);
            $crawler->addContent($content);
            $crawler->filterXPath('//ul[@class="sitemap"]/li/a')->each(function (Crawler $node, $i) {
                $url = $node->attr('href');

                $urlArr = explode('-', $url);
                $productId = isset($urlArr[count($urlArr) - 1]) ? (int)trim($urlArr[count($urlArr) - 1], '.html') : 0;

                if ($productId) {
                    if (!$this->isExists($productId)) {
                        $product = new Product();
                        $product->setProductId($productId);
                        $product->setStatus(Product::STATUS_CREATED);
                        $product->setTitle($node->text());
                        $product->setDescription('test');
                        $product->setUrl($url);
                        $product->setDateCreated(new \DateTime("now"));
                        $product->setDateUpdated(new \DateTime("now"));

                        /** @var EntityManager $em */
                        $em = $this->getContainer()->get('doctrine')->getEntityManager();
                        $em->persist($product);
                        $em->flush();
                    } else {
                        echo 'Already added: ' . $productId . "\n";
                    }


                } else {
                    echo 'ProductId=0; url=' . $url . "\n";
                }
            });
        }
    }

    /**
     * Парсинг инфо продукта
     * @param OutputInterface $output
     */
    protected function parseProductsInfo(OutputInterface $output)
    {
        $output->writeln('Получение всех продуктов');
        $doctrine = $this->getContainer()->get('doctrine');
        /** @var Product[] $products */
        $products = $doctrine
            ->getRepository('AppBundle:Product')
            ->findBy(['status' => Product::STATUS_CREATED]);
        $output->writeln('Получено из базы продуктов: ' . count($products));
        $index = 0;
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine')->getEntityManager();
        foreach ($products as $product) {
            $index++;
            $output->writeln('Product ' . $index);
            $url = $product->getUrl();
            $output->writeln($url);

            $content = $this->getContent($url);

            $crawler = new Crawler();
            $crawler->addContent($content);
            $descriptionNode = $crawler->filterXPath('//div[@class="product-right"]/div[@itemtype="http://schema.org/Product"]//span[@itemprop="description"]');
            if ($descriptionNode->count()) {
                $description = $descriptionNode->text();
                echo $description;
                $product->setDescription($description);
            }
            $priceNode = $crawler->filterXPath('//div[@class="product-right"]/div[@itemtype="http://schema.org/Product"]//span[@class="price"]');
            if ($priceNode->count()) {
                $price =  $priceNode->attr('content');
                echo $price;
                $product->setPrice($price);
                $currencyNode = $crawler->filterXPath('//div[@class="product-right"]/div[@itemtype="http://schema.org/Product"]//span[@class="price"]/span[@class="priceCurrency"]');
                if ($currencyNode->count()) {
                    $currency = $currencyNode->text();
                    echo $currency;
                    $product->setCurrency($currency);
                }
            }

            $colorsNode = $crawler->filterXPath('//div[@class="product-right"]/div[@itemtype="http://schema.org/Product"]//ul[@class="pdp-colors"]/li/a');
            if ($colorsNode->count()) {
                $colors = $colorsNode->each(function (Crawler $node, $i) {
                    return $node->attr('title');
                });

                $product->setColors(implode(',', $colors));
            }

            $imagesNode = $crawler->filterXPath('//div[@class="product-left"]//ul[@id="thumblist"]/li/a');

            if ($imagesNode->count()) {
                $images = $imagesNode->each(function (Crawler $node, $i) {
                    return $node->attr('rel');
                });
                foreach ($images as $image) {
                    $imageInfo = json_decode($image);
                    $productImage = new ProductImage();
                    $productImage->setProductId($product->getProductId());
                    $largeImage = $imageInfo->largeimage;
                    $largeImage = explode('?', $largeImage);
                    $largeImage = isset($largeImage[0]) ? $largeImage[0] : $imageInfo->largeimage;
                    $productImage->setBigUrl($largeImage);
                    $em->persist($productImage);
                }
            }

            $product->setStatus(Product::STATUS_UPDATED);
            $product->setDateUpdated(new \DateTime("now"));

            $em->persist($product);
            $em->flush();
        }

    }

    protected function getContent($url)
    {
        $contextOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ];

        $content = file_get_contents($url, false, stream_context_create($contextOptions));
        return $content;
    }

    /**
     * Существует ли уже такой продукт в таблице
     * @param int $productId
     * @return bool
     */
    public function isExists($productId)
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine')->getEntityManager();

        /** @var Query $query */
        $query = $em
            ->createQuery('SELECT 1 FROM AppBundle:Product p WHERE p.productId = :productId')
            ->setParameter('productId', $productId)
            ->setMaxResults(1)
        ;

        return (count($query->getResult()) != 0);
    }

    /**
     * Парсинг последнего номера страницы
     * @return int
     */
    public function parseLastPage()
    {
        $content = $this->getContent(static::SITEMAP_URL);

        $crawler = new Crawler();
        $crawler->addContent($content);
        $lastPage =  (int)$crawler->filterXPath('//a[@class="last"]')->text();
        return $lastPage;
    }

    protected function parseImages(OutputInterface $output)
    {
        $output->writeln('Получение картинок');
        $doctrine = $this->getContainer()->get('doctrine');
        /** @var ProductImage[] $images */
        $images = $doctrine
            ->getRepository('AppBundle:ProductImage')
            ->findAll();

        $i = 0;
        // download and save the image to the folder
        foreach ($images as $image) {
            $i++;
            $output->writeln('Image #' . $image->getId());
            $path = trim('../images/' . basename($image->getBigUrl()), '?v=0');
            $file = file_get_contents($image->getBigUrl());
            $insert = file_put_contents($path, $file);
            if (!$insert) {
                throw new \Exception('Failed to write image: ' . $image->getBigUrl());
            }
        }
    }

    protected function parseBigImages(OutputInterface $output)
    {
        $output->writeln('Получение тайлов картинок');
        $doctrine = $this->getContainer()->get('doctrine');
        /** @var ProductImage[] $images */
        $images = $doctrine
            ->getRepository('AppBundle:ProductImage')
            ->findBy(['id' => 1]);

        $i = 0;
        // download and save the image to the folder
        foreach ($images as $image) {
            $i++;
            $output->writeln('Image #' . $image->getId());
            $productId = $image->getProductId();
            $folder = self::FOLDER . $productId . '/';
            if (!is_dir($folder)) {
                mkdir($folder);
            }

            $canMerge = true;
            for ($x = 0; $x <= 7; $x++) {
                for ($j = 0; $j <=7; $j++) {
                    $path = $folder . trim(basename($image->getBigUrl()), '?v=0') . '_3_' . $x . '_' . $j . '.jpg';
                    $fileUrl = 'https://17ce85a4f632d4aaaa0861aafda0ba70.lswcdn.net/tiles/' . $productId . '/' . trim(basename($image->getBigUrl()), '?v=0') . '/3f/' . $x . '/' . $j . '.jpg';
                    if ($this->fileExists($fileUrl)) {
                        $file = file_get_contents($fileUrl);

                        $output->writeln('Downloading file: ' . $fileUrl);
                        $insert = file_put_contents($path, $file);
                        if (!$insert) {
                            throw new \Exception('Failed to write image: ' . $image->getBigUrl());
                        }
                    } else {
                        $canMerge = false;
                    }
                }
            }
            $output->writeln('Склеивание картинок');
            if ($canMerge) {
                $this->imageMerge($productId);
            } else {
                $output->writeln('Невозможно склеить тайлы');
            }
            if($i==1){
                die;
            }
            $output->writeln('--------------------------------------------------------------------');
        }
    }

    /**
     * Склеивание тайлов
     * @param $productId
     */
    public function imageMerge($productId)
    {
        $imageSrcWidth = 256;
        $imageSrcHeight = 256;
        $folder = self::FOLDER . $productId . '/';
        $destFileName = $productId . '.jpg';
        $dest = imagecreatetruecolor($imageSrcWidth * 8, $imageSrcHeight * 8);
        imagejpeg($dest, $folder . $destFileName);

        for($i = 7; $i >= 0; $i--){
            for($j = 0; $j <= 7; $j++){
                $fileName = $folder . $productId . '_7.jpg_3_' . $j . '_' . $i . '.jpg';
                $src = imagecreatefromjpeg($fileName);
                $dstY = (7 - $i) * $imageSrcWidth;
                $dstX = $j * $imageSrcHeight;
                dump($dstX);
                dump($dstY);
                dump('--------');
                imagecopy($dest, $src, $dstX, $dstY, 0, 0, $imageSrcWidth, $imageSrcHeight);
                imagedestroy($src);
                unlink($fileName);
            }
        }
        imagejpeg($dest, $folder . $destFileName);
        imagedestroy($dest);
    }

    protected function fileExists($url)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        $result = curl_exec($curl);
        var_dump($result);die;
        $ret = false;

        if ($result !== false) {
            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($statusCode == 200) {
                $ret = true;
            }
        }
        curl_close($curl);

        return $ret;
    }

}
