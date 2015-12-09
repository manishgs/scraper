<?php namespace Scraper;

class Grab {

    protected $client;

    protected $url;

    protected $crawler;

    protected $validImageSize = 200;

    public function __construct()
    {
        $this->client = new Goutte\Client();
    }

    /**
     * @param $url
     */
    public function setUrl($url)
    {
        $this->url = $this->getValidUrl($url);
    }

    /**
     * Get Url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Get Domain
     *
     * @param bool $full
     * @return string
     */
    public function getDomain($full = false)
    {
        $parse = parse_url($this->url);

        $domain = $parse['host'];

        if($full) {
            return $this->getValidUrl($domain);
        } else {
            return ltrim($domain, 'www.');
        }
    }

    /** Get info
     *
     * @throws Exception
     */
    public function getInfo()
    {
        $this->crawler = $this->scrapeData();

        return (object) array(
            'domain'      => $this->getDomain(),
            'url'         => $this->getUrl(),
            'favicon'     => $this->getFavIcon(),
            'title'       => $this->getTitle(),
            'description' => $this->getDescription(),
            'content'     => $this->getContent(),
            'image'       => $this->getFeaturedImage()
        );
    }

    public function getFavIcon()
    {
        $img = '';
        $twitterMeta = 'link[rel="shortcut icon"], link[rel="apple-touch-icon"], link[rel="icon"]';
        $node = $this->crawler->filter($twitterMeta)->first();

        if($node->count()) {
            $img = $node->attr('href');
            if(!empty($img)) {
                $img = $this->getValidImageUrl($img);
            }
        }

        return $img;
    }

    /**
     * Get Page Url
     *
     * @return string
     */
    public function getTitle()
    {
        $title = $this->getMetaTitle();

        if(!empty($title)) {
            return $title;
        }

        if($this->crawler->filter('title')->text() != '') {
            return $this->crawler->filter('title')->text();
        } elseif($this->crawler->filter('h1')->count() == 1) {
            return $this->crawler->filter('h1')->text();
        } else {
            return $this->getUrl();
        }
    }

    /**
     * Get Meta Title
     */
    public function getMetaTitle()
    {
        $title = '';
        $twitterMeta = 'meta[property="og:title"], meta[name="og:title"],meta[name="twitter:title"],meta[property="twitter:title"]';
        $node = $this->crawler->filter($twitterMeta);

        if($node->count()) {
            $title = $node->first()->attr('content');
        }

        return $title;
    }


    /**
     * Get Top Node
     */
    public function getTopNode()
    {
        $count = array();
        $this->crawler->filter('p, tr, pre')->each(function ($node, $i) use (&$count) {
            $count[str_word_count(strip_tags($node->parents()->text()))] = $node->parents()->first();
        });
        krsort($count);
        $node = $this->crawler;

        $new = array_values($count);

        if(isset($new[0])) {
            $node = $new[0];
        }

        return $node;
    }

    /**
     * Get Page Description
     *
     * @param null $limit_word
     * @return string
     */
    public function getDescription($limit_word = null)
    {
        $description = $this->getMetaDescription();

        if(empty($description)) {
            $this->crawler->filter('p')->each(function ($node, $i) use (&$description) {
                $description .= trim($node->text());
            });
        }

        if(!is_null($limit_word)) {
            $description = $this->trimWord($description, $limit_word);
        }

        return $description;
    }

    /**
     * Get page Content
     *
     * @return string
     */
    public function getContent()
    {
        return $this->getTopNode()->html();
    }

    /**
     * Get Page Images
     *
     * @param int $limit
     * @return array
     */
    public function getImages($limit = 1)
    {
        $total_image = $this->getTopNode()->filter('img')->count();
        $images = array();
        if($total_image) {
            $this->getTopNode()->filter('img')->each(function ($node, $i) use (&$images, $limit) {
                if(count($images) < $limit) {
                    $img = $node->attr('src');
                    if(!empty($img)) {
                        $imageUrl = $this->getValidImageUrl($img);
                        list($width, $height) = getimagesize($imageUrl);
                        if($this->isValidImageSize($width, $height)) {
                            $images[] = $imageUrl;
                        }
                    }
                }
            });
        }

        return $images;
    }

    /**
     * Get Meta Image
     *
     * @return string
     */
    public function getMetaImage()
    {
        $img = '';
        $twitterMeta = 'meta[property="og:image"],meta[name="og:image"],meta[property="twitter:image"],meta[name="twitter:image"],meta[property="twitter:image:src"],meta[name="twitter:image:src"]';
        $node = $this->crawler->filter($twitterMeta)->first();
        if($node->count()) {
            $img = $node->attr('content');
            if(!empty($img)) {
                $img = $this->getValidImageUrl($img);
            }
        }

        return $img;
    }

    /**
     * Get Featured image
     *
     * @return string
     */
    public function getFeaturedImage()
    {
        $img = $this->getMetaImage();
        if(!empty($img)) {
            return $img;
        }

        $img = $this->getImages(1);
        if(!empty($img)) {
            return $img[0];
        }

        return '';
    }

    /**
     * Get valid Url
     * @param $url
     * @return string
     */
    protected function getValidUrl($url)
    {
        if(strpos($url, 'http') === false) {
            $url = 'http://' . $url;
        }

        return $url;
    }

    /**
     * Scrape html from url
     *
     * @return \Symfony\Component\DomCrawler\Crawler
     * @throws Exception
     */
    private function scrapeData()
    {
        try {
            $crawler = $this->client->request('GET', $this->url);
            $status_code = $this->client->getResponse()->getStatus();
            if($status_code == 200) {
                $content_type = $this->client->getResponse()->getHeader('Content-Type');
                if(strpos($content_type, 'text/html') !== false) {

                    return $crawler;
                } else {
                    throw new Exception('Content is not html.');
                }
            }
            throw new Exception('Could get content from the url.');
        } catch (Exception $ex) {
            throw new Exception('Invalid Url.');
        }
    }

    /**
     * Get Valid Image Url
     *
     * @param $url
     * @return string
     */
    private function getValidImageUrl($url)
    {
        if(strpos($url, 'http') === false) {
            return trim($this->getDomain(true), '/') . '/' . trim($url, '/');
        }

        return $url;
    }

    /**
     * Check for valid image size
     * @param $width
     * @param $height
     * @return bool
     */
    private function isValidImageSize($width, $height)
    {
        if($width > $this->validImageSize || $height > $this->validImageSize) {
            return true;
        }

        return false;
    }

    /**
     * Get Trim word
     *
     * @param $string
     * @param $count
     * @return string
     */
    private function trimWord($string, $count)
    {
        $original_string = $string;
        $words = explode(' ', $original_string);

        if(count($words) > $count) {
            $words = array_slice($words, 0, $count);
            $string = implode(' ', $words);
        }

        return $string;
    }

    /**
     * Get Meta Description
     *
     * @return string
     */
    private function getMetaDescription()
    {
        $meta_description = '';
        $this->crawler->filter('meta')->each(function ($node, $i) use (&$meta_description) {
            if($node->attr('name') == 'description') {
                $meta_description = $node->attr('content');
            }

            if(empty($meta_description) && $node->attr('property') == 'og:description') {
                $meta_description = $node->attr('content');
            }

            if(empty($meta_description) && $node->attr('name') == 'twitter:description') {
                $meta_description = $node->attr('content');
            }
        });

        return $meta_description;
    }
}
