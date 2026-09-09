<?php

echo "Shawn: " . password_hash('shawn', PASSWORD_DEFAULT);
echo "<br>";
echo "Bits: " . password_hash('bits', PASSWORD_DEFAULT);

?>