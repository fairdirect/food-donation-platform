<?php

class Business_ProductsController extends Zend_Controller_Action
{

    public function init(){
        Model_User::refreshAuth(); // make sure our data is up to date
        $this->user = Zend_Auth::getInstance()->getIdentity();      
    }

    public function indexAction()
    {
        $this->view->headTitle("Produkt-Verwaltung");          
        $this->view->products = $this->user->getShop()->getProducts(null, null, false, false, false, false);
    }

    public function editAction(){
        $form = new Business_Form_Products();
        $request = $this->getRequest();

        $attributeTypes = array('additive' => 'Zusätze', 'allergen' => 'Allergene', 'event' => 'Anlässe', 'flavor' => 'Geschmack', 'manipulation' => 'Veränderungen');

        $id = $request->getParam('id');
        if($id){ // editing
            $product = Model_Product::find($id);
            if($product->getShop()->user_id != $this->user->id){
                exit('Forbidden!'); // prevent user from writing products not owned by them
            }
        }
        else{ // new product
            $product = new Model_Product(array('shop_id' => $this->user->getShop()->id));
        }

        if($request->getParam('Speichern')){
            if(array_key_exists('shop_id', $request->getPost())){
                exit('Forbidden!'); // prevent user from writing forbidden fields
            }

            if($form->isValid($request->getPost())){
                $product->init($request->getPost());
                if($request->getPost('unlimited_stock')){
                    $product->stock = NULL; // stock === null implies unlimited stock
                }
                else{
                    if(!$request->getPost('stock') && !is_int($request->getPost('stock'))){
                        $product->stock = 0;
                    }
                }
                if(!$request->getPost('best_before')){
                    $product->best_before = NULL;
                }
                try{
                     $product->save();
                } catch(Exception $e){
                    echo $e->getMessage();
                    exit();
                }

                $attributes = array();
                Model_ProductAttribute::deleteForProduct($product->id);
                foreach($attributeTypes as $aName => $aLabel){
                    if($request->getPost($aName)){
                        $attrGroup = $request->getPost($aName);
                        foreach($attrGroup as $a){
                            $attributes[] = $a;
                        }
                    }
                }
                $product->setAttributes($attributes);

                Model_ProductPrice::deleteForProduct($product->id);
                $counter = 0;
                while(++$counter <= 3){
                    $price = new Model_ProductPrice(array('product_id' => $product->id));
                    $price->is_wholesale = 'false';
                    $price->quantity = $request->getPost('normal_amount_' . $counter);
                    $price->unit_type_id = $request->getPost('normal_unit_' . $counter);
                    $price->contents = $request->getPost('normal_content_' . $counter);
                    $price->content_type_id = $request->getPost('normal_content_type_' . $counter);
                    $price->value = $request->getPost('normal_euro_' . $counter) + ($request->getPost('normal_cent_' . $counter) / 100);
                    try{
                        $price->save();
                    } catch(Exception $e){
                 
                    }
                }

                $counter = 0;
                while(++$counter <= 3){
                    $price = new Model_ProductPrice(array('product_id' => $product->id));
                    $price->is_wholesale = 'true';
                    $price->quantity = $request->getPost('wholesale_amount_' . $counter);
                    $price->unit_type_id = $request->getPost('wholesale_unit_' . $counter);
                    $price->contents = $request->getPost('wholesale_content_' . $counter);
                    $price->content_type_id = $request->getPost('wholesale_content_type_' . $counter);
                    $price->value = $request->getPost('wholesale_euro_' . $counter) + ($request->getPost('wholesale_cent_' . $counter) / 100);
                    try{
                        $price->save();
                    } catch(Exception $e){

                    }
                }

                $this->_helper->redirector('index');
            }
        }
        else{
            if($id){
                $product = Model_Product::find($id);
                $attributes = $product->getAttributes();
                if($product){
                    $category = $product->getCategory();
                    $group = $category->getProductGroup();
                    $mainCat = Model_MainCategory::find($group->main_category);
                    $typeGroups = $mainCat->getGroups(false, false, false, false);
                    $groupElements = array();
                    foreach($typeGroups as $gr){
                        $groupElements[$gr->id] = $gr->name;
                    }
                    $form->getElement('group_id')->addMultiOptions($groupElements);
                    $groupCategories = Model_ProductCategory::findByGroup($group->id, false, false, false, false, false);
                    $categoryElements = array();
                    foreach($groupCategories as $cat){
                        $categoryElements[$cat->id] = $cat->name;
                    }
                    $form->getElement('category_id')->addMultiOptions($categoryElements);
                    $formData = array_merge($product->toFormArray(), array('type' => $mainCat->id, 'group_id' => $group->id, 'category_id' => $category->id));

                    $form->populate($formData);
                }
            }
            else{
                $allergenes = $form->getElement('allergen');
                $allergenes->setAttrib('checked', 'checked');
            }
        }
        $this->view->form = $form;
    }

    public function picturesAction(){
        $id = $this->getRequest()->getParam('id');
        if(!$id){
            $this->_helper->redirector('index');
        }
        $product = Model_Product::find($id);
        if(!$product){
            $this->_helper->redirector('index');
        }
        if($product->getShop()->user_id != $this->user->id){
            exit('Forbidden!'); // prevent user from writing products not owned by them
        }

        $this->view->product = $product;
        if($_FILES){
            $extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $filename = 'products_' . $product->id;
            $filepath = $_SERVER['DOCUMENT_ROOT'] . Zend_Registry::get('config')->pictureupload->path . Zend_Registry::get('config')->productpictures->dir;
            $counter = 0;
            $filenamesInUse = array();
            foreach($product->getPictures() as $pic){
                $filenamesInUse[] = $pic->filename;
            }
            while(in_array($filename . '.' . $extension, $filenamesInUse)){
                $filename = str_replace('-' . ($counter), '', $filename);
                $filename .= '-' . (++$counter);
            }
	    $filename .= '.' . $extension;

            try{
                $img = new Imagick();
                $img->readImage($_FILES['file']['tmp_name']);
                $img->writeImage($filepath . 'original/' . $filename);
                $img->thumbnailImage(380, null);
                $img->writeImage($filepath . '380w/' . $filename);
                $img->thumbnailImage(380, 285, true);
                $img->writeImage($filepath . '380x285/' . $filename);
                $img->thumbnailImage(174, 136, true);
                $img->writeImage($filepath . '174x136/' . $filename);
                $img->thumbnailImage(90, 68, true);
                $img->writeImage($filepath . '90x68/' . $filename);
                $img->thumbnailImage(36, 27, true);
                $img->writeImage($filepath . '36x27/' . $filename);
                
                $picture = new Model_Picture(array('filename' => $filename));
                $picture->save();
                $product->addPicture($picture->id);
            } catch(Exception $e){
                exit($e->getMessage());
            }

            $ret = '<tr><td><input type="checkbox" name="imagesDelete[]" value="' . $picture->id . '" /></td><td><img style="width:120px;" src="/img/products/174x136/' . $filename . '" alt=""></td><td><input class="check_default" type="checkbox" onclick="setDefaultPic(' . $picture->id . ')" value="' . $picture->id . '" name="is_default"></tr>';
            exit(json_encode(array('data' => $ret)));
        }
    }

    public function deletepictureAction(){
        $picture = Model_Picture::find($this->getRequest()->getPost('id'));
        if(!$picture){
            exit(json_encode(array('suc' => false)));
        }
        $products = $picture->getAssociatedProducts();
        foreach($products as $product){
            if($product->getShop()->user_id == $this->user->id){ // make sure the picture belongs to the user
                $picture->delete();
                break;
            }
        }
        exit(json_encode(array('suc' => true)));
    }

    public function deleteAction(){
        $id = $this->getRequest()->getParam('id');
        $product = Model_Product::find($id);
        if($product->getShop()->user_id != $this->user->id){
            exit('Forbidden!'); // prevent user from writing products not owned by them
        }
        $product->delete();
        $this->_helper->redirector('index');
    }

    public function activateAction(){
        $id = $this->getRequest()->getParam('id');
        $product = Model_Product::find($id);
        $product->active = true;
        $product->save();
        $this->_helper->redirector('index');
    }

    public function deactivateAction(){
        $id = $this->getRequest()->getParam('id');
        $product = Model_Product::find($id);
        $product->active = false;
        $product->save();
        $this->_helper->redirector('index');
    }

    public function setdefaultpictureAction(){
        $product_id = $this->getRequest()->getParam('product_id');
        if(!$product_id){
            exit(json_encode(array('suc' => false, 'msg' => 'Keine Produkt-ID!')));
        }
        $product = Model_Product::find($product_id);
        if(!$product){
            exit(json_encode(array('suc' => false, 'msg' => 'Kein Produkt!')));
        }
        if($product->getShop()->user_id != $this->user->id){
            exit(json_encode(array('suc' => false, 'msg' => 'Nicht erlaubt!'))); // prevent user from writing products not owned by them
        }
        $pictures = $product->getPictures();
        $picturesToProduct = array();
        foreach($pictures as $pic){
            $picturesToProduct[] = $pic->id;
        }
        if(!$this->getRequest()->getParam('id') || !in_array($this->getRequest()->getParam('id'), $picturesToProduct)){
            exit(json_encode(array('suc' => false, 'msg' => 'Bild nicht zugeordnet')));
        }
        
        $product->main_picture_id = $this->getRequest()->getParam('id');
        $product->save();

        exit(json_encode(array('suc' => true)));


    }
}
