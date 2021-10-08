<?php
namespace SimpleHtml\Common\File;

/*
 * Handles file browsing
 * Oriented towards graphics files for CKEditor
 *
 * Author: doug@unlikelysource.com
 * License: BSD
 * Revision Date: 2021-10-06
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 * * Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 * * Redistributions in binary form must reproduce the above
 *   copyright notice, this list of conditions and the following disclaimer
 *   in the documentation and/or other materials provided with the
 *   distribution.
 * * Neither the name of the  nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */
use ArrayIterator;
use FilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SimpleHtml\Common\Generic\Messages;
use InvalidArgumentException;
class Browse
{

    const DEFAULT_IMG_DIR   = BASE_DIR . '/public/images';
    const DEFAULT_THUMB_DIR = BASE_DIR . '/public/images/thumb';
    const DEFAULT_THUMB_URL = '/images/thumb';
    const DISPLAY_ROWS      = 3;
    public $errors        = [];
    public $config        = [];
    public $images        = [];
    public $allowed       = [];
    public $img_dir       = '';
    public $img_url       = '';
    public $thumb_dir     = '';
    public $thumb_url     = '';
    /**
     * @param array $config : file upload information; looks for a key 'UPLOADS'
     */
    public function __construct(array $config)
    {
        $this->config = $config['UPLOADS'] ?? [];
        if (empty($this->config))
            throw new InvalidArgumentException(Upload::UPLOAD_ERROR_MISSING);
        $this->img_dir = $this->config['upload_dir'] ?? self::DEFAULT_IMG_DIR;
        $this->allowed = $this->config['allowed_ext'] ?? Upload::UPLOAD_DEFAULT_EXT;
        $this->img_url = $this->config['url'] ?? Upload::UPLOAD_DEFAULT_URL;
        $this->thumb_dir = $this->config['thumb_dir'] ?? self::DEFAULT_THUMB_DIR;
        $this->thumb_url = $this->config['thumb_url'] ?? self::DEFAULT_THUMB_URL;
    }

    /**
     * Handles file browsing
     *
     * @return string $html : HTML table with images + thumbnails
     */
    public function handle()
    {
        $list  = new ArrayIterator($this->getListOfImages());
        $html  = '<table>';
        $count = 1000;
        while ($list->valid()) {
            $html .= '<tr>';
            for ($x = self::DISPLAY_ROWS; $x > 0; $x--) {
                if ($list->valid()) {
                    $key = $list->key();
                    $fn  = $list->current();
                    $list->next();
                    $thumb_fn = $this->getThumbFnFromImageFn($fn);
                    $thumb_url = $this->getThumbUrlFromImageUrl($key);
                    if (!file_exists($thumb_fn))
                        $this->makeThumbnail($fn, $thumb_fn);
                    $id   = 'img_' . $count++;
                    $html = '<a href="#" onclick="returnFileUrl(\'' . $id . '\')">'
                          . '<img src="' . $thumb_url . '" alt="' . $key . '" />'
                          . '</a>'
                          . '<input type="hidden" id="' . $id . '" value="' . $fn . '" />';
                } else {
                    $html .= '<td>&nbsp;</td>';
                }
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    /**
     * Creates thumbnail for an image
     * NOTE: requires the GD extension
     *
     * @param string $fn : image filename
     * @param string $thumb_fn : image filename for thumbnail
     * @return bool TRUE if thumbnail created OK; FALSE otherwise
     */
    public function makeThumbnail(string $fn, string $thumb_fn)
    {
        // create GD image
        // scale to 50 x 50
        // get thumb FN
        // save
    }

    /**
     * Returns thumbnail filename from image filename
     *
     * @param string $img_fn    : image filename
     * @return string $thumb_fn : thumbnail filename
     */
    public function getThumbFnFromImageFn(string $img_fn)
    {
        return str_replace($this->img_dir, $this->thumb_dir, $img_fn);
    }

    /**
     * Returns thumbnail URL from image URL
     *
     * @param string $img_url    : image filename
     * @return string $thumb_url : thumbnail filename
     */
    public function getThumbUrlFromImageUrl(string $img_url)
    {
        return str_replace($this->img_url, $this->thumb_url, $img_url);
    }

    /**
     * Returns list of pages from starting point HTML_DIR
     * Note: HTML_DIR is a global constant defined in /public/index.php
     *
     * @param string $path  : starting path (if other than HTML_DIR
     * @return array $pages : [URL key => full path, URL key => full path]
     */
    public function getListOfImages(string $path = NULL)
    {
        $path = $path ?? $this->img_dir;
        if (empty($this->images)) {
            $iter = new RecursiveDirectoryIterator($path);
            $itIt = new RecursiveIteratorIterator($iter);
            $filt = new class ($itIt, $this->allowed) extends FilterIterator {
                public $allowed = [];
                public function __construct($iter, $allowed)
                {
                    parent::__construct($iter);
                    $this->allowed = $allowed;
                }
                public function accept()
                {
                    $ok  = FALSE;
                    $obj = $this->current() ?? FALSE;
                    if ($obj && $obj instanceof SplFileInfo) {
                        $ext = strtolower($obj->getExtension());
                        $ok  = in_array($ext, $this->allowed);
                    }
                    return $ok;
                }
            };
            foreach ($filt as $name => $obj) {
                $url = $this->img_url . '/' . str_replace($path, '', $name);
                $this->images[$url] = $name;
            }
            ksort($this->images);
        }
        return $this->images;
    }
}
