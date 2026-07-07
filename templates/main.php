<?php

declare(strict_types=1);

/** @var array $_ */
?>
<div id="worktimepunch-root" class="worktimepunch-page">
	<h2>WorkTimePunch</h2>
	<?php if ($_['worktimeEnabled']): ?>
		<p>WorkTime ist aktiv. Die Schaltflaechen in der oberen Nextcloud-Leiste werden fuer angemeldete Nutzer geladen.</p>
	<?php else: ?>
		<p>WorkTime ist nicht aktiv. WorkTimePunch deaktiviert sich selbst.</p>
	<?php endif; ?>
</div>
