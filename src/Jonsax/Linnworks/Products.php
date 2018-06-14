<?php
namespace Jonsax\Linnworks;

use ZendDiagnosticsTest\TestAsset\Check\ThrowException;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Select;
use Zend\Db\ResultSet\HydratingResultSet;
use Zend\Db\TableGateway\Feature\RowGatewayFeature;
use Jonsax\Linnworks\Factory;

class Products extends Factory
{




    var $stockUpdateChunkSize = 150;

    var $headers = false;



    public function getProductCache($linnworks_product, $mintsoft_product=false,$source=null) {
        
        $rowset= $this->getProductsTable()->select("sku='" . $linnworks_product->SKU . "'");
        
        if ($rowset->current()) {
            $product = $rowset->current();
        } else {
            $this->getProductsTable()->insert(array(
                "sku"=>$linnworks_product->SKU,
                "linnworks_id"=>$linnworks_product->Id,
                "barcode"=>$linnworks_product->Barcode,
                "name"=>$linnworks_product->Title,
                "mintsoft_id"=>($mintsoft_product?$mintsoft_product->ID:null),
                "source"=> $source
            ));
            
            $rowset= $this->getProductsTable()->select("sku='".$linnworks_product->SKU."'");
            $product = $rowset->current();
            
        }
        
        
        return $product;
        
        
    }
    

    public function getLinnworksStock() {

        $query = $this->getLinnworksProductsTable()
        ->getAdapter()
        ->query('truncate  ' . $this->getLinnworksProductsTable()->getTable());
        // $query->where('warehouse = ' .$warehouseId );
        
        $result = $query->execute();
        
        $perpage = 1;
        $page=1;
        
        $more = true;
        
        try {
        
            while ($more) {
                
              //  $data = "entriesPerPage=".$perpage."&loadCompositeParents=true&loadVariationParents=true&pageNumber=".$page."&Requirements=[0,0]&searchTypes=[0,0]";
                $data = "loadCompositeParents=true&loadVariationParents=true&entriesPerPage=200&pageNumber={$page}&dataRequirements=[0,1,2,3,4,5,6,7]&searchTypes=[0,0]";
                
                $result = $this->sendPost("Stock/GetStockItemsFull", $data);
                $this->addLinnworksItems($result);
                
                $page++;
            }
                
        
        } catch (\Exception $e) {
            
 //           print_r($e);
            
            die("error" . $e->getMessage());
        }
        
        
    }
    
    public function addLinnworksItems($result) {
        
        foreach ($result as $stock_item) {
         
            $this->getLinnworksProductsTable()->insert(
                    array(
                            'item_number' => $stock_item->ItemNumber,
                            'title' => $stock_item->ItemTitle,
                            'purchase_price' => $stock_item->PurchasePrice,
                            'retail_price' => $stock_item->RetailPrice,
                            'qty' => 0,
                            'stock_item_id' => $stock_item->StockItemId,
                            'stock' => json_encode($stock_item->StockLevels),
                            'barcode' => $stock_item->BarcodeNumber
                    ));
        }
        
    }

    
    public function checkMissing() {
        
        $sqlSelect = $this->getLinnworksProductsTable()
        ->getSql()
        ->select();
        $sqlSelect->join('nisbets',
            'linnworks_products.item_number = nisbets.sku',
            array('*'), 'left');
        
        $sqlSelect->where("nisbets.sku is null");
        
        $resultSet = $this->getLinnworksProductsTable()->selectWith($sqlSelect);
        
        foreach ($resultSet as $stocklistItem) {
            
            $stock = json_decode($stocklistItem['stock']);
            foreach ($stock as $stocklocation) {
                
                $stock[$stocklocation->Location->StockLocationId] = $stocklocation->StockLevel;
                
            }
            
            if (isset($stock[$this->stock_locations['uropa']]) &&  $stock[$this->stock_locations['uropa']] >0 ) {

                $sku = $stocklistItem['item_number'];

                $stockLevels = array(
                    array(
                        "SKU" => $sku,
                        "LocationId" => $this->stock_locations['uropa'],
                        "Level" => 0
                    )
                    
                );
                
                $data = "stockLevels=" . json_encode($stockLevels);
                
                if ($this->debug) echo "\r\nupdate missing sku from nisbets feed " . $sku . " qty = " . $stock[$this->stock_locations['uropa']] . "  sourceqty = " . 0;
                
                try {
                    $result = $this->sendPost("Stock/SetStockLevel", $data);
                    
                    // update our cache too
               //     $this->getLinnworksProductsTable()->update(array("qty"=>0), "item_number='".$sku."'");
                    
                }catch(\Exception $e) {
                    error_log("sku = ".$stocklistItem['sku'] . " stock update error: ". $e->getMessage() );
                    
                }
 
            }
            
            
        }
        
        
    }
    
    
}