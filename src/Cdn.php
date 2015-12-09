<?php namespace Visualplus\Cdn;

use Image;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp as Adapter;
use Visualplus\Cdn\CdnLog;

class Cdn
{
    private $driver = "";
    private $domain = "";
    private $default_path = "";

    private $filesystem = "";
    private $isConnected = false;

    public function __construct()
    {
        $conf = config('filesystems.disks');

        $this->filesystem = new Filesystem(new Adapter([
            'host' => $conf['ftp']['host'],
            'username' => $conf['ftp']['username'],
            'password' => $conf['ftp']['password'],
            'ssl'   => false,

            'timeout' => 10,
        ]));

        try {
            $this->filesystem->getAdapter()->getConnection();
            $this->isConnected = true;
        } catch (\RuntimeException $e) {
        }

        $this->driver = config('cdn.driver');
        $this->domain = config('cdn.domain');
        $this->default_path = config('cdn.default_path');
    }

    /**
     * 파일이 존재하는지 검사
     * @param $path
     * @param $filename
     * @param $size
     * @return bool
     */
    public function exists($path, $filename, $size)
    {
        // ftp 연결이 안됐을 경우 false를 리턴
        if ($this->isConnected == false) return false;

        if (CdnLog::where('path', '=', $path)->where('filename', '=', $filename)->where('size', '=', $size)->count() > 0) {
            return true;
        }

        return false;
    }

    /**
     * 파일 업로드
     * @param $path
     * @param $extension
     * @param $content
     * @param string $size
     * @param string $filename
     * @return string
     */
    public function upload($path, $extension, $content, $size = '', $filename = '')
    {
        if ($this->isConnected == false) return "";

        if ($filename == '') {
            do {
                $filename = uniqid();
            } while ($this->exists($path, $filename . '.' . $extension, $size));
            $filename .= '.' . $extension;
        }

        $upload_path = $path;
        if ($size != '') $upload_path .= '/' . $size;
        $upload_path .= '/' . $filename;

        // Storage::disk($this->driver)->put($this->default_path . $upload_path, $content);
        $this->filesystem->put($this->default_path . $upload_path, $content);
        CdnLog::create([
            'path'      => $path,
            'filename'  => $filename,
            'size'      => $size,
        ]);

        return $filename;
    }

    /**
     * 파일 삭제
     * @param $path
     * @param $filename
     */
    public function delete($path, $filename)
    {
        if ($this->isConnected == false) return;

        $cdnLogs = CdnLog::where('path', '=', $path)->where('filename', '=', $filename)->get();
        foreach ($cdnLogs as $cdnLog) {
            $path = $cdnLog->path;
            if ($cdnLog->size != '') $path .= '/' . $cdnLog->size;
            $path .= '/' . $cdnLog->filename;

            //Storage::disk($this->driver)->delete($this->default_path . $path);

            if ($this->filesystem->has($this->default_path . $path)) {
                $this->filesystem->delete($this->default_path . $path);
            }

            $cdnLog->delete();
        }
    }

    /**
     * 저장된 URL
     * @param $path
     * @param $filename
     * @param string $size
     * @return string
     */
    public function getURL($path, $filename, $size = '')
    {
        if ($this->isConnected == false) return "";
        $cdnLog = CdnLog::where('path', '=', $path)->where('filename', '=', $filename)->where('size', '=', $size)->first();

        if ($cdnLog) {
            // 파일이 존재함
            $url = $path;
            if ($size != '') $url .= '/' . $size;
            $url .= '/' . $filename;

            return $this->domain . '/' . $url;
        } else {
            // 파일이 존재하지 않음
            if ($size != '') {
                // 찾으려는 파일이 섬네일이라면..
                $parentFile = CdnLog::where('path', '=', $path)->where('filename', '=', $filename)->first();
                if ($parentFile) {
                    // bmp 파일은 그냥 리턴
                    if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) == 'bmp') {
                        return $this->domain . '/' . $path . '/' . $filename;
                    }

                    // 부모 파일이 있어야 섬네일 생성 가능
                    // $src = Image::make(Storage::disk($this->driver)->get($this->default_path . $path . '/' . $filename));
                    if ($this->filesystem->has($this->default_path . $path . '/' . $filename)) {
                        $src = Image::make($this->filesystem->read($this->default_path . $path . '/' . $filename));

                        // 원본 이미지 사이즈 구하기
                        $img_w = $src->width();
                        $img_h = $src->height();

                        // 원하는 이미지 사이즈
                        $s = EXPLODE('x', $size);
                        $width = $s[0];
                        $height = $s[1];

                        // 원본 이미지보다 작은 사이즈를 원하면 원본 리사이징
                        if ($img_w > $width) {
                            $src->resize($width, $height);
                        }

                        // 이미지 업로드
                        $filename = $this->upload($path, pathinfo($filename, PATHINFO_EXTENSION), $src->response()->original, $size, $filename);

                        $url = $path;
                        $url .= '/' . $size;
                        $url .= '/' . $filename;

                        return $this->domain . '/' . $url;
                    }
                }
            }
            return '';
        }
    }

    /**
     * 다운로드 URL 가져오기
     * @param $path
     * @param $filename
     * @param $orgname
     * @return string
     */
    public function getDownloadURL($path, $filename, $orgname)
    {
        $url = $this->getURL($path, $filename, '');

        return $url;
    }

    /**
     * 파일 사이즈 구하기
     * @param $path
     * @param $filename
     * @return bool|false|int
     */
    public function getSize($path, $filename)
    {
        if ($this->isConnected == false) return -1;

        return $this->filesystem->getSize($this->default_path . $path . '/' . $filename);
    }
}