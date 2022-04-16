{*
* Project : everpsvoucheronorder
* @author Team EVER
* @copyright Team EVER
* @license   Tous droits réservés / Le droit d'auteur s'applique (All rights reserved / French copyright law applies)
* @link https://www.team-ever.com
*}
<div class="panel everheader">
    <div class="panel-heading">
        <i class="icon icon-smile"></i> {l s='Ever Voucher on order' mod='everpsvoucheronorder'}
    </div>
    <div class="panel-body">
        <div class="col-md-6">
            <a href="#everbottom" id="evertop">
               <img id="everlogo" src="{$image_dir|escape:'htmlall':'UTF-8'}/logo.png" style="max-width: 120px;">
            </a>
            <strong>{l s='Welcome to Ever Voucher on order !' mod='everpsvoucheronorder'}</strong><br />{l s='Please configure your this form to set vouchers' mod='everpsvoucheronorder'}<br />
            <p>
                <strong>{l s='Click on our logo to go direct to bottom' mod='everpsvoucheronorder'}</strong>
            </p>
        </div>
        <div class="col-md-6">
            <p class="alert alert-warning">
                {l s='This module is free and will always be ! You can support our free modules by making a donation by clicking the button below' mod='everpsvoucheronorder'}
            </p>
            <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
            <input type="hidden" name="cmd" value="_s-xclick" />
            <input type="hidden" name="hosted_button_id" value="3LE8ABFYJKP98" />
            <input type="image" src="https://www.team-ever.com/wp-content/uploads/2019/06/appel_a_dons-1.jpg" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Bouton Faites un don avec PayPal" />
            <img alt="" border="0" src="https://www.paypal.com/fr_FR/i/scr/pixel.gif" width="1" height="1" />
            </form>
        </div>
    </div>
</div>
