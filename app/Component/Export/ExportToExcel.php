<?php

namespace App\Component\Export;

use App\Component\Excel\ExcelException;
use App\Exception\SystemErrorException;
use App\Component\Excel\Excel;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Filesystem\FilesystemFactory;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use SplFileObject;
use function Hyperf\Support\env;

class ExportToExcel implements ExportInterface
{

    #[Inject]
    protected Filesystem $filesystem;

    #[Inject]
    protected FilesystemFactory $filefactory;

    function extension(): string
    {
        return 'xlsx';
    }

    private function mkdir($filepath): bool
    {
        return is_dir($filepath) || mkdir($filepath, 0777, true);
    }

    /**
     * @throws ExportException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function exportToFile(iterable $data, ?array $title = null): string
    {
        if (empty($data) && empty($title)) {
            throw new ExportException('未导出任何数据');
        }
        $filepath = BASE_PATH . '/runtime/exportfile/' . date('Ymd');
        try {
            if (!$this->mkdir($filepath)) {
                throw new SystemErrorException('创建文件夹失败');
            }
        } catch (Exception $e) {
            throw new SystemErrorException($e->getMessage());
        }
        $filename = uniqid() . time() . '.' . $this->extension();
        $path = $filepath . '/' . $filename;

        try {
            $excel = new Excel();
            $excel->setSheetData($data, $title);
            $excel->save($path);
        } catch (ExcelException $e) {
            throw new ExportException($e->getMessage());
        }
        return $path;
    }

    /**
     * @throws ExportException
     * @throws FilesystemException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function exportToOss(iterable $data, ?array $title = null, ?string $path = null): string
    {
        $excel_file = $this->exportToFile($data, $title);

        $filename = md5(uniqid('', true) . time()) . '.' . $this->extension();
        $origin_filepath = $path ?? "export/" . date("Ymd");
        $origin_filepath = trim($origin_filepath, '/') . '/' . $filename;

        $oss = $this->filefactory->get('oss');
        $stream = fopen($excel_file, 'r+');
        $oss->writeStream($origin_filepath, $stream);
        is_resource($stream) && fclose($stream);

        return env('OSS_IMG_URL', 'https://image.dingxinwen.com') . '/' . $origin_filepath;
    }

}