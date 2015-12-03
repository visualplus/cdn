<?php namespace Visualplus\Cdn;

use Storage;

class Cdn
{
    private $driver = "";
    public function __construct()
    {
        $this->driver = coinfig('cdn.driver');
    }

    /**
     * 파일 업로드
     *
     * @param $path
     * @param $file
     */
    public function Upload($path, $file)
    {
        Storage::disks($this->driver)->put($path, $file);
    }

    /**
     * 파일 삭제
     *
     * @param $path
     */
    public function Delete($path)
    {
        Storage::disks($this->driver)->delete($path);
    }
}