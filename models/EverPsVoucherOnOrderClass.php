<?php
/**
 * Project : everpsvoucheronorder
 * @author Team Ever
 * @copyright Team Ever
 * @license   Tous droits réservés / Le droit d'auteur s'applique (All rights reserved / French copyright law applies)
 * @link https://www.team-ever.com
 */

class EverPsVoucherOnOrderClass extends ObjectModel
{
    public $id_everpsvoucheronorder;
    public $id_customer;
    public $id_order;
    public $email;
    public $voucher_code;

    public static $definition = array(
        'table' => 'everpsvoucheronorder',
        'primary' => 'id_everpsvoucheronorder',
        'multilang' => false,
        'fields' => array(
            'id_customer' => array(
                'type' => self::TYPE_INT,
                'lang' => false,
                'validate' => 'isunsignedInt',
                'required' => true,
            ),
            'id_order' => array(
                'type' => self::TYPE_INT,
                'lang' => false,
                'validate' => 'isunsignedInt',
                'required' => true,
            ),
            'email' => array(
                'type' => self::TYPE_STRING,
                'lang' => false,
                'validate' => 'isEmail',
            ),
            'voucher_code' => array(
                'type' => self::TYPE_STRING,
                'lang' => false,
                'validate' => 'isString',
            ),
        )
    );

    public static function getByCustomer($id_customer)
    {
        $sql = new DbQuery();
        $sql->select('id_everpsvoucheronorder');
        $sql->from('everpsvoucheronorder');
        $sql->where(
            'id_customer = "' . (int) $id_customer . '"'
        );
        return Db::getInstance()->getValue($sql);
    }
}
