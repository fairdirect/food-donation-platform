<?php

class AjaxController extends Zend_Controller_Action
{

    public function init(){
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout()->disableLayout();
    }

    public function indexAction(){
        exit(json_encode(array('suc' => false, 'message' => 'No action specified')));
    }

    public function addtoshoppingcartAction(){
        $request = $this->getRequest();
        if($request->getPost('price_id') && $request->getPost('quantity')){
            $price = Model_ProductPrice::find($request->getPost('price_id'));
            $existingChanged = Model_ShoppingCart::getRunningShoppingCart()->changeQuantity($price->product_id, $request->getPost('price_id'), $request->getPost('quantity'));
            if(!$existingChanged){
                Model_ShoppingCart::getRunningShoppingCart()->addProduct($price->product_id, $request->getPost('price_id'), $request->getPost('quantity'));
            }
            exit(json_encode(array('suc' => true, 'message' => '')));
        }
        else{
            exit(json_encode(array('suc' => false, 'message' => 'Incomplete request')));
        }
    }

    public function changequantityAction(){
        $request = $this->getRequest();
        if($request->getPost('price_id') && $request->getPost('quantity')){
            $price = Model_ProductPrice::find($request->getPost('price_id'));
            Model_ShoppingCart::getRunningShoppingCart()->changeQuantityAbsolute($price->product_id, $request->getPost('price_id'), $request->getPost('quantity'));
            exit(json_encode(array('suc' => true, 'message' => '')));
        }
        else{
            exit(json_encode(array('suc' => false, 'message' => 'Incomplete request')));
        }
    }

    public function deletefromcartAction(){
        $request = $this->getRequest();
        if($request->getPost('price_id')){
            $price = Model_ProductPrice::find($request->getPost('price_id'));
            Model_ShoppingCart::getRunningShoppingCart()->deleteProduct($price->product_id, $request->getPost('price_id'), $request->getPost('quantity'));
            exit(json_encode(array('suc' => true, 'message' => '')));
        }
        else{
            exit(json_encode(array('suc' => false, 'message' => 'Incomplete request')));
        }
    }

    public function getshoppingcartAction(){
        $request = $this->getRequest();
        $shoppingCart = Model_ShoppingCart::getRunningShoppingCart();
        exit($this->view->partial('/partials/shoppingcart.phtml', array('shoppingCart' => $shoppingCart)));
    }

    public function getgroupsAction(){
        $type = $this->getRequest()->getParam('type');
        $ret = '<option value=""></option>';

        switch($type){
            case 'groceries':
                $groups = Model_ProductGroup::getByType('groceries', false, false, false, false);
                break;
            case 'drugstore':
                $groups = Model_ProductGroup::getByType('drugstore', false, false, false, false);
                break;
            default:
                exit();
        }
        foreach($groups as $gr){
            $ret .= '<option value="' . $gr->id . '">' . $gr->name . '</option>';
        }
        exit($ret);
    }

    public function getcategoriesAction(){
        $groupID = $this->getRequest()->getParam('group');
        $ret = '<option value=""></option>';

        $categories = Model_ProductCategory::findByGroup($groupID, false, false, false, false, true);
        foreach($categories as $cat){
            $ret .= '<option value="' . $cat->id . '">' . $cat->name . '</option>';
        }
        exit($ret);
    }

    public function setlanguageAction(){
        $session = new Zend_Session_Namespace('Default');
        switch($this->getRequest()->getParam('language_id')){
            case 'de':       
                $session->language = 'de';
                break;
            case 'it':
                $session->language = 'it';
                break;
            default:
                $session->language = 'de';
                break;
        }

    }
}

