<?php namespace Visualplus\Cdn;

use Storage;
use Image;
use Visualplus\Cdn\CdnLog;

class Cdn
{
    private $driver = "";
    private $domain = "";
    private $default_path = "";

    public function __construct()
    {
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
        if (CdnLog::where('path', '=', $path)->where('filename', '=', $filename)->where('size', '=', $size)->count() > 0) {
            return true;
        }

        return false;
    }

    /**
     * 파일 업로드
     * @param $path
     * @param $filename
     * @param $content
     * @return bool
     */
    public function upload($path, $filename, $content, $size = '')
    {
        if ($this->exists($path, $filename, $size)) {
            return false;
        } else {
            $upload_path = $path;
            if ($size != '') $upload_path .= '/' . $size;
            $upload_path .= '/' . $filename;

            Storage::disk($this->driver)->put($this->default_path . $upload_path, $content);
            CdnLog::create([
                'path'      => $path,
                'filename'  => $filename,
                'size'      => $size,
            ]);
            return true;
        }
    }

    /**
     * 파일 삭제
     * @param $path
     * @param $filename
     */
    public function delete($path, $filename)
    {
        $cdnLogs = CdnLog::where('path', '=', $path)->where('filename', '=', $filename)->get();
        foreach ($cdnLogs as $cdnLog) {
            $path = $cdnLog->path;
            if ($cdnLog->size != '') $path .= '/' . $cdnLog->size;
            $path .= '/' . $cdnLog->filename;

            Storage::disk($this->driver)->delete($this->default_path . $path);

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
    public function getImageURL($path, $filename, $size = '')
    {
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
                    // 부모 파일이 있어야 섬네일 생성 가능
                    $src = Image::make(Storage::disk($this->driver)->get($this->default_path . $path . '/' . $filename));

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
                    if ($this->upload($path, $filename, $src->response()->original, $size)) {
                        $url = $path;
                        $url .= '/' . $size;
                        $url .= '/' . $filename;

                        return $this->domain . '/' . $url;
                    } else {
                        dd('asdf');
                    }
                }
            }
            return 'image not found';
        }
    }
}