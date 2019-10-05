<?php
 namespace Home\Model;
 use Think\Model;
/**
 ** community info 
 */
class LoanModel extends Model {
    /* return 0, OK, -1 COMUNITY NOT FOUND, 1 COMUNITY NOT IN SERVICE */
    public function showHello()
    {
        echo "hello LoanModel";
    }
    
    public function addLoanDetail($loan_detail){
        $m=M('loan');
        return $m->add($loan_detail);
    }
    
    public function addLoanAll($loan_detail_arr){
        $m=M('loan');
        return $m->addAll($loan_detail_arr);
    }
    public function loanExist($list_id)
    {   
        $m=M('loan');
        $result=$m->where('ListingId="'.$list_id.'"')->find();
        return !empty($result);
    }

    public function getLoanDetail($list_id){
        $m=M('loan');
        return $m->where('ListingId="'.$list_id.'"')->find();
    }
    

};
?>
