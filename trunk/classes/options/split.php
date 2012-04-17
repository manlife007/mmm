<?php
function rl_split() {
	global $PHP_SELF, $list, $options, $L;
?>
<form method="post" action="<?php echo $PHP_SELF; ?>">
	<input type="hidden" name="act" value="split_go" />
	<table align="center">
		<tr>
			<td>
				<table>
<?php
	for ($i = 0; $i < count($_GET ["files"]); $i++) {
		$file = $list [$_GET ["files"] [$i]];
?>
						<tr>
							<td align="center">
								<input type="hidden" name="files[]" value="<?php echo $_GET ["files"] [$i]; ?>" /> <b><?php echo basename($file ["name"]); ?></b>
							</td>
						</tr>
						<tr>
							<td><?php echo $L->say['part_size']; ?>:&nbsp;<input type="text" name="partSize[]" size="2" value="<?php echo ($_COOKIE ["partSize"] ? $_COOKIE ["partSize"] : 10); ?>" />&nbsp;MB</td>
						</tr>
						<?php if ($options['download_dir_is_changeable']) { ?>
						<tr>
							<td><?php echo $L->say['saveto']; ?>:&nbsp;<input type="text" name="saveTo[]" size="40" value="<?php echo addslashes($options['download_dir']); ?>" /></td>
						</tr>
						<?php } ?>
							<tr>
								<td><input type="checkbox" name="del_ok" <?php echo $options['disable_to']['act_del'] ? 'disabled="disabled"' : 'checked="checked"'; ?> />&nbsp;<?php echo $L->say['del_source_aft_split']; ?></td>
							</tr>
							<tr>
								<td align="left">CRC32 generation mode:<br />
									<?php if (function_exists('hash_file')) { ?>
										<input type="radio" name="crc_mode[<?php echo $i; ?>]" value="hash_file" checked="checked" />&nbsp;Use hash_file (Recommended)<br />
									<?php } ?>
										<input type="radio" name="crc_mode[<?php echo $i; ?>]" value="file_read" />&nbsp;Read file to memory<br />
										<input type="radio" name="crc_mode[<?php echo $i; ?>]" value="fake"<?php if (!function_exists('hash_file')) { echo ' checked="checked"';} ?> />&nbsp;Fakecrc
								</td>
							</tr>
							<tr>
								<td></td>
							</tr>
<?php
	}
?>
					</table>
				</td>
				<td><input type="submit" value="<?php echo $L->say['split_file']; ?>" /></td>
			</tr>
			<tr>
				<td></td>
			</tr>
		</table>
	</form>
	<?php
}

function split_go() {
	global $list, $options, $L;
	for ($i = 0; $i < count($_POST ["files"]); $i++) {
		$split_ok = true;
		$file = $list [$_POST ["files"] [$i]];
		$partSize = round(($_POST ["partSize"] [$i]) * 1024 * 1024);
		$saveTo = ($options['download_dir_is_changeable'] ? stripslashes($_POST ["saveTo"] [$i]) : realpath($options['download_dir'])) . '/';
		$dest_name = basename($file ["name"]);
		$fileSize = filesize($file ["name"]);
		$totalParts = ceil($fileSize / $partSize);
		$crc = ($_POST ['crc_mode'] [$i] == 'file_read') ? dechex(crc32(read_file($file ["name"]))) : (($_POST ['crc_mode'] [$i] == 'hash_file' && function_exists('hash_file')) ? hash_file('crc32b', $file ["name"]) : '111111');
		$crc = str_repeat("0", 8 - strlen($crc)) . strtoupper($crc);
		echo $L->say["start_split"] . " <b>" . basename($file ["name"]) . "</b> parts of " . bytesToKbOrMbOrGb($partSize) . ", Using Method - Total Commander...<br />";
		echo $L->say["tot_part"] . ": <b>" . $totalParts . "</b><br /><br />";
		for ($j = 1; $j <= $totalParts; $j++) {
			if (file_exists($saveTo . $dest_name . '.' . sprintf("%03d", $j))) {
				echo $L->say["split_error"] . " " . $L->say["piece_exist"] . "<b>" . $dest_name . '.' . sprintf("%03d", $j) . "</b> !<br /><br />";
				continue 2;
			}
		}
		if (file_exists($saveTo . $dest_name . '.crc')) {
			echo $L->say["split_error"] . " " . $L->say["crc_exist"] . "<b>" . $dest_name . '.crc' . "</b> !<br /><br />";
		} elseif (!is_file($file ["name"])) {
			echo $L->say["split_error"] . " " . $L->say["src_notfound"] . "<b>" . $file ["name"] . "</b> !<br /><br />";
		} elseif (!is_dir($saveTo)) {
			echo $L->say["split_error"] . " " . $L->say["dir_inexist"] . "<b>" . $saveTo . "</b> !<br /><br />";
		} elseif (!@write_file($saveTo . $dest_name . ".crc", "filename=" . $dest_name . "\r\n" . "size=" . $fileSize . "\r\n" . "crc32=" . $crc . "\r\n")) {
			echo $L->say["split_error"] . " CRC Error<b>" . $dest_name . ".crc" . "</b> !<br /><br />";
		} else {
			$time = getNowzone(@filemtime($saveTo . $dest_name . '.crc'));
			while (isset($list [$time])) {
				$time++;
			}
			$list [$time] = array("name" => $saveTo . $dest_name . '.crc', "size" => bytesToKbOrMbOrGb(filesize($saveTo . $dest_name . '.crc')), "date" => $time);
			$split_buffer_size = 2 * 1024 * 1024;
			$split_source = @fopen($file ["name"], "rb");
			if (!$split_source) {
				echo $L->say["crc_error_open"] . " <b>" . $file ["name"] . "</b> !<br /><br />";
				continue;
			}
			for ($j = 1; $j <= $totalParts; $j++) {
				$split_dest = @fopen($saveTo . $dest_name . '.' . sprintf("%03d", $j), "wb");
				if (!$split_dest) {
					echo $L->say["error_open_file"] . " <b>" . $dest_name . '.' . sprintf("%03d", $j) . "</b> !<br /><br />";
					$split_ok = false;
					break;
				}
				$split_write_times = floor($partSize / $split_buffer_size);
				for ($k = 0; $k < $split_write_times; $k++) {
					$split_buffer = fread($split_source, $split_buffer_size);
					if (fwrite($split_dest, $split_buffer) === false) {
						echo $L->say["error_write_file"] . " <b>" . $dest_name . '.' . sprintf("%03d", $j) . "</b> !<br /><br />";
						$split_ok = false;
						break;
					}
				}
				$split_rest = $partSize - ($split_write_times * $split_buffer_size);
				if ($split_ok && $split_rest > 0) {
					$split_buffer = fread($split_source, $split_rest);
					if (fwrite($split_dest, $split_buffer) === false) {
						echo $L->say["error_write_file"] . " <b>" . $dest_name . '.' . sprintf("%03d", $j) . "</b> !<br /><br />";
						$split_ok = false;
					}
				}
				fclose($split_dest);
				if ($split_ok) {
					$time = getNowzone(@filemtime($saveTo . $dest_name . '.' . sprintf("%03d", $j)));
					while (isset($list [$time])) {
						$time++;
					}
					$list [$time] = array("name" => $saveTo . $dest_name . '.' . sprintf("%03d", $j), "size" => bytesToKbOrMbOrGb(filesize($saveTo . $dest_name . '.' . sprintf("%03d", $j))), "date" => $time);
				}
			}
			fclose($split_source);
			if ($split_ok) {
				if ($_POST["del_ok"] && !$options['disable_to']['act_del']) {
					if (@unlink($file ["name"])) {
						unset($list [$_POST ["files"] [$i]]);
						echo $L->say["source_del"] . "<br /><br />";
					} else {
						echo $L->say["source_file_is"] . "<b>" . $L->say["_not_del"] . "</b><br /><br />";
					}
				}
			}
			if (!updateListInFile($list)) {
				echo $L->say["couldnt_upd_list"] . "<br /><br />";
			}
		}
	}
}
?>