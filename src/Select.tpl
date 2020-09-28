SELECT <?php echo $SELECT ?>
<?php if (!empty($FROM)): ?>
 FROM <?php echo $FROM ?>
<?php endif ?>
<?php if (!empty($JOIN)): ?>
 <?php echo $JOIN ?>
<?php endif ?>
<?php if (!empty($WHERE)): ?>
 WHERE <?php echo $WHERE ?>
<?php endif ?>
<?php if (!empty($GROUP_BY)): ?>
 GROUP BY <?php echo $GROUP_BY ?>
<?php endif ?>
<?php if (!empty($HAVING)): ?>
 HAVING <?php echo $HAVING ?>
<?php endif ?>
<?php if (!empty($ORDER_BY)): ?>
 ORDER BY <?php echo $ORDER_BY ?>
<?php endif ?>
<?php if (!empty($LIMIT)): ?>
 LIMIT <?php echo $LIMIT ?><?php if (!empty($OFFSET)): ?> OFFSET <?php echo $OFFSET ?><?php endif ?>
<?php endif ?>
