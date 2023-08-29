<?php

namespace App\Component\Export;

use App\Exception\SystemErrorException;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Filesystem\FilesystemFactory;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use SplFileObject;
use function Hyperf\Support\env;
use function array_values, array_keys, is_array;

class ExportToCsv implements ExportInterface
{

    #[Inject]
    protected Filesystem $filesystem;

    #[Inject]
    protected FilesystemFactory $filefactory;

    function extension(): string
    {
        return 'csv';
    }

    private function mkdir($filepath): bool
    {
        return is_dir($filepath) || mkdir($filepath, 0777, true);
    }

    /**
     * @throws ExportException
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
        $file = new SplFileObject($filepath . '/' . $filename, 'w+x');
        if (!empty($title)) {
            $file->fputcsv(array_values($title));
        }
        foreach ($data as $row) {
            $line = [];
            if (!empty($title)) {
                foreach (array_keys($title) as $index) {
                    $line[] = isset($row[$index]) ?
                        (is_array($row[$index]) ? ($row[$index]['value'] ?? '') : strval($row[$index]))
                        : '';
                }
            } else {
                foreach ($row as $item) {
                    $line[] = is_array($item) ? ($item['value'] ?? '') : strval($item);
                }
            }
            $file->fputcsv(array_values($line));
        }
        return $file->getRealPath();
    }

    /**
     * @throws ExportException
     * @throws FilesystemException
     */
    public function exportToOss(iterable $data, ?array $title = null, ?string $path = null): string
    {
        $csv_file = $this->exportToFile($data, $title);

        $filename = md5(uniqid('', true) . time()) . '.' . $this->extension();
        $origin_filepath = $path ?? "export/" . date("Ymd");
        $origin_filepath = $origin_filepath . '/' . $filename;

        $oss = $this->filefactory->get('oss');
        $stream = fopen($csv_file, 'r+');
        $oss->writeStream($origin_filepath, $stream);
        is_resource($stream) && fclose($stream);

        return env('OSS_IMG_URL', 'https://image.dingxinwen.com') . '/' . $origin_filepath;
    }

}