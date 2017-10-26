<?php
use app\core\App;
use app\models\Callback;
use app\models\DataProduct;

/**
 * Class ControllerCatalogPrides
 */
class ControllerCatalogLoadFromExcel extends Controller {
    const NAME_LIST_PRODUCTS = 'Export Products Sheet';
    const NAME_LIST_GROUPS = 'Export Groups Sheet';
	private $error = array();
    public $data = [];

	public function index() {
        $this->data['nameFile'] = '1.xlsx';

        if (isset($this->request->post['do_load_from_excel']) && $this->request->post['do_load_from_excel'] == 'y') {
            ini_set('max_execution_time', 0);
            ini_set('allow_url_fopen', 1);
            set_time_limit(0);
            $this->loadModels();
            $onlyImages = false;
            if (isset($this->request->post['do_image'])) {
                $onlyImages = true;
            }
            $this->session->data['do_load_from_excel']['success'] = $this->loadFromFile(DIR_UPLOAD . '/' . $this->request->post['nameFile'],$onlyImages);
            $this->response->redirect($this->url->link('catalog/load_from_excel', ['token' => $this->session->data['token']]));
        }
        $this->setOutput();

    }

    protected function loadModels() {
        $this->load->model('catalog/product');
        $this->load->model('catalog/manufacturer');
        $this->load->model('catalog/attribute');
        $this->load->model('catalog/option');

        DataProduct::$ocModel = $this->model_catalog_product;
        DataProduct::$ocModelManufacturer = $this->model_catalog_manufacturer;
        DataProduct::$ocModelAttribute = $this->model_catalog_attribute;
        DataProduct::$ocModelOption = $this->model_catalog_option;

    }

    protected function setOutput() {

        $this->data['error_warning'] = '';
        $this->data['success'] = '';
        if (isset($this->session->data['do_load_from_excel'])) {
            if ($this->session->data['do_load_from_excel']['success']) {
                $this->data['success'] = 'Файл прочитан';
            } else {
                $this->data['error_warning'] = 'Ошибка чтения файла';
            }
            unset($this->session->data['do_load_from_excel']);
        }

        $this->data['title'] = 'Загрузка товаров с Excel';

        $this->document->setTitle($this->data['title']);
        $this->data['header'] = $this->load->controller('common/header');
        $this->data['column_left'] = $this->load->controller('common/column_left');
        $this->data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('catalog/load_from_excel', $this->data));
    }

	protected function readFile($excelFile) {

        $objReader = PHPExcel_IOFactory::createReader('Excel2007');
        $objPHPExcel = $objReader->load($excelFile);
        $arrayData = [];
        $arrayData['products'] = [];
        $arrayData['groups'] = [];
        foreach ($objPHPExcel->getWorksheetIterator() as $worksheet) {
            $title = $worksheet->getTitle();
            if ($title == self::NAME_LIST_PRODUCTS) {
                $arrayData['products'] = $worksheet->toArray();
            } elseif ($title == self::NAME_LIST_GROUPS) {
                $arrayData['groups'] = $worksheet->toArray();
            } else {
                $arrayData[$title] = $worksheet->toArray();
            }
        }
        return $arrayData;
    }

    protected function loadFromFile($file, $onlyImages = false) {
        $excel = $this->readFile($file);
        unset($excel['products'][0]);
        $groups = [];
        foreach ($excel['groups'] as $group) {
            $groups[$group[0]] = $group[7];
        }
        DataProduct::$groupsExcel = $groups;
        if ($onlyImages) {
            $this->saveImages($excel['products']);
            return true;
        }
        $this->saveProducts($excel['products']);
        return true;
    }

    protected function saveProducts($dataProducts) {
        DataProduct::$productsExcel = $dataProducts;
        $savedProducts = $this->cache->get('savedProductsFromExcel');
        foreach ($dataProducts as $dataProduct) {
            if (empty($dataProduct[3])) {
                continue;
            }

            $product = new DataProduct();
            $product->loadFromExcelData($dataProduct);
            if (isset($savedProducts[$product->name])) {
                $product->id = $savedProducts[$product->name];
            }
            $product->save();
            $savedProducts[$product->name] = $product->id;
            App::$debug->d($dataProduct);
            break;
        }
        $this->cache->set('savedProductsFromExcel', $savedProducts);
        die();
    }

    protected function saveImages($dataProducts) {
        $this->data['loadImgError'] = [];
        $this->data['loadImgSucces'] = [];
        $images = [];
        foreach ($dataProducts as $dataProduct) {
            $rowImages = explode(',',$dataProduct[15]);
            foreach ($rowImages as $rowImage) {
                $images[] = $rowImage;
            }
        }
        $product = new DataProduct();
        $i = 0;
        foreach ($images as $image) {
            $i++;
            $this->saveImageRecursive($product, $image,$i);
            if ($i == 5) {
                break;
            }
        }
        App::$debug->d(count($this->data['loadImgError']));
        App::$debug->d($this->data['loadImgSucces']);
        App::$debug->dDie($this->data['loadImgError']);
    }
    protected function saveImageRecursive(DataProduct $product,$image,$i,$trying = 0 ){
        $res = $product->saveImage($image);
        $trying++;
        if (!$this->getResultSaveImage($res,$image, $i)) {
            unlink(DIR_IMAGE . $res);
            return;
            $res = $product->saveImage2($image);
            if (!$this->getResultSaveImage($res,$image, '2.' . $i)) {
                unlink(DIR_IMAGE . $res);
                $res = $product->saveImage3($image);
                if (!$this->getResultSaveImage($res,$image, '3.' . $i)) {
//                    unlink(DIR_IMAGE . $res);
                }
            }
        }
    }

    protected function getResultSaveImage($res,$image, $i) {
        $size = getimagesize(DIR_IMAGE . $res);
        if (!$size) {
            $this->data['loadImgError'][$i] = ['link' => $image, 'load_file' => $res];
            return false;
        } else {
            $this->data['loadImgSucces'][$i] = ['link' => $image, 'load_file' => $res];
            return true;
        }
    }
}
?>