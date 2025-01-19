<table style="width:100%;border:0;<?php if (empty($qrcode)) { ?>padding-left:15px;<?php } ?>font-size:8px;">
	<tr>
		<?php if (!empty($qrcode)) { ?>
			<td width="<?= $qrcodeSize; ?>px">
				<img src="data:image/png;base64,<?= $qrcode; ?>" style="width:<?= $qrcodeSize; ?>px"/>
			</td>
		<?php } ?>
		<td style="vertical-align: bottom;padding: 0px 0px 15px 0px;line-height:1.5em;">
			<a href="<?= $linkToSite; ?>" style="text-decoration: none;color:unset;"><?= $signedBy; ?></a>
			<?php if ($validateIn) { ?>
				<br>
				<a href="<?=$validationSite; ?>" style="text-decoration: none;color:unset;"><?= str_replace('%s', $validationSite, $validateIn); ?></a>
			<?php } ?>
		</td>
	</tr>
</table>
