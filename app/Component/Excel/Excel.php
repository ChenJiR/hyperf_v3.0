<?php

namespace App\Component\Excel;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use function is_array, array_keys, array_shift, file_exists;

class Excel
{
    /**
     * @var Spreadsheet
     */
    private $spreadsheet;

    /**
     * @var string
     */
    private $excel_path;


    public function __construct(?string $excel_path = null)
    {
        $this->setExcelPath($excel_path);
    }

    /**
     * @param mixed $excel_path
     */
    public function setExcelPath(?string $excel_path = null): void
    {
        $this->excel_path = $excel_path;
        $this->spreadsheet = ($excel_path && file_exists($excel_path)) ? IOFactory::load($excel_path) : new Spreadsheet();
    }

    /**
     * @return string
     */
    public function getExcelPath(): string
    {
        return $this->excel_path;
    }

    private function getSheet(?string $sheet = null)
    {
        return $sheet ? $this->spreadsheet->getSheetByName($sheet) : $this->spreadsheet->getActiveSheet();
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws ExcelException
     */
    public function addSheet(string $sheet_title): Excel
    {
        if ($this->spreadsheet->sheetNameExists($sheet_title)) {
            throw new ExcelException('已存在sheet名' . $sheet_title);
        }
        $newsheet = new Worksheet(null, $sheet_title);
        $this->spreadsheet->addSheet($newsheet);
        $this->spreadsheet->setActiveSheetIndexByName($sheet_title);
        return $this;
    }

    /**
     * @throws ExcelException|\PhpOffice\PhpSpreadsheet\Exception
     */
    public function setSheetData(iterable $data = [], ?array $title = null, ?string $sheet = null): static
    {
        if (empty($data) && empty($title)) {
            throw new ExcelException('未导出任何数据');
        }
        $worksheet = $this->getSheet($sheet);
        if (!empty($title)) {
            $worksheet->fromArray(array_values($title));
            $startCell = 'A2';
        } else {
            $startCell = 'A1';
        }

        [$startColumn, $startRow] = Coordinate::coordinateFromString($startCell);
        foreach ($data as $rowData) {
            $currentColumn = $startColumn;
            if (!empty($title)) {
                $line = [];
                foreach (array_keys($title) as $index) {
                    $line[] = $rowData[$index] ?? null;
                }
                $rowData = $line;
            }
            foreach ($rowData as $cellValue) {
                if ($cellValue != null) {
                    $cell = $worksheet->getCell($currentColumn . $startRow);
                    if (is_array($cellValue)) {
                        $style = $cell->getStyle();
                        if (isset($cellValue['value'])) {
                            $cell->setValue(strval($cellValue['value']));
                            isset($cellValue['number']) && $style->getNumberFormat()->setFormatCode($cellValue['number']);
                            isset($cellValue['wraptext']) && $style->getAlignment()->setWrapText($cellValue['wraptext']);
                            isset($cellValue['url']) && $cell->getHyperlink()->setUrl($cellValue['url']);
                            if (isset($cellValue['font'])) {
                                isset($cellValue['font']['bold']) && $style->getFont()->setBold($cellValue['font']['bold']);
                                isset($cellValue['font']['color']) && $style->getFont()->getColor()->setARGB($cellValue['font']['color']);
                            }
                            if (isset($cellValue['dict']) && isset($cellValue['dict']['format'])) {
                                $validation = $cell->getDataValidation();
                                $validation->setType(DataValidation::TYPE_LIST);
                                $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                                $validation->setAllowBlank($cellValue['dict']['allow_blank'] ?? true);
                                if (isset($cellValue['dict']['input_message']) && $cellValue['dict']['input_message'] == false) {
                                    $validation->setShowInputMessage(false);
                                } else {
                                    $validation->setShowInputMessage(true);
                                    $validation->setPromptTitle('请选择');
                                    $validation->setPrompt($cellValue['dict']['input_message'] ?? '请从列表中选择一个值');
                                }
                                if (isset($cellValue['dict']['error_message']) && $cellValue['dict']['error_message'] == false) {
                                    $validation->setShowErrorMessage(false);
                                } else {
                                    $validation->setShowErrorMessage(true);
                                    $validation->setErrorTitle('选择错误');
                                    $validation->setError($cellValue['dict']['error_message'] ?? '值不在列表中');
                                }
                                $validation->setShowDropDown(true);
                                $validation->setFormula1('"' . implode(',', $cellValue['dict']['format']) . '"');
                            }
                        }
                    } else {
                        $cell->setValue(strval($cellValue));
                    }
                }
                ++$currentColumn;
            }
            ++$startRow;
        }

        return $this;
    }

    public function getSheetData(?string $sheet = null, bool $with_title = false): array
    {
        $worksheet = $this->getSheet($sheet);

        $data = $worksheet->toArray();

        if ($with_title) {
            if (!empty($data)) {
                $title = array_shift($data);
            } else {
                $data = $title = [];
            }
            return [$title, $data];
        } else {
            return $data;
        }
    }

    /**
     * @throws ExcelException
     * @throws Exception
     */
    public function save(?string $save_path = null): Excel
    {
        $save_path = $save_path ?? $this->excel_path;
        if (!$save_path) {
            throw new ExcelException('导出文件地址不能为空');
        }
        if (file_exists($save_path)) {
            throw new ExcelException('文件已存在');
        }
        $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');
        $writer->save($save_path);
        return $this;
    }

}