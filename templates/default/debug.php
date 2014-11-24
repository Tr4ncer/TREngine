<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
    <head>
        <link rel="stylesheet" href="templates/default/style.css" type="text/css" />
        <?php echo TREngine\Engine\Core\CoreHtml::getInstance()->getMetaHeaders(); ?>
    </head>
    <body>
        <div class="error">
            <b><?php echo $errorMessageTitle; ?></b>
            <br /><br />

            <?php if (!empty($errorMessage)) { ?>
                Error details:<br />
                <ul>
                    <?php foreach ($errorMessage as $value) { ?>
                        <?php if (empty($value)) { ?>
                            <li><hr/></li>
                        <?php } else { ?>
                            <li><?php echo $value; ?></li>
                            <?php
                        }
                    }
                    ?>
                </ul>
            <?php } ?>

            <?php echo TREngine\Engine\Core\CoreHtml::getInstance()->getMetaFooters(); ?>
        </div>
    </body>
</html>