<?php
require __DIR__.'/messaging.php';
$db->exec("ALTER TABLE whatsapp_broadcasts ADD COLUMN created_at TEXT DEFAULT '2026-10-31 10:00:00'");
$history=$broadcast->history(1,1);
check(count($history)===3,'history lists existing project snapshots');
check($broadcast->history(2,1)===[],'history excludes other project');
check((int)$db->query('SELECT COUNT(*) FROM whatsapp_deliveries')->fetchColumn()===1,'viewing history never sends or reserves deliveries');
echo "Broadcast history: $checks checks passed\n";
