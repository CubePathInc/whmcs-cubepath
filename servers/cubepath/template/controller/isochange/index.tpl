{include file="modules/servers/cubepath/template/element/flashMessages.tpl"}
{include file="modules/servers/cubepath/template/element/mainButtons.tpl"}

<div class="row">
	<div class="col-sm-12">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">ISO Management</h3>
			</div>
			<div class="panel-body">
				{if isset($mountedIsoId) && $mountedIsoId}
					<div class="alert alert-info">
						<strong>Currently Mounted ISO ID:</strong> {$mountedIsoId}
						<form method="post" style="display: inline; margin-left: 15px;">
							<input type="hidden" name="action" value="unmount" />
							<input type="hidden" name="token" value="{$token}" />
							<button type="submit" class="btn btn-sm btn-warning"
									onclick="return confirm('Are you sure you want to unmount the ISO?');">
								Unmount ISO
							</button>
						</form>
					</div>
				{/if}

				{if isset($isos) && is_array($isos) && $isos|@count > 0}
					<form method="post">
						<input type="hidden" name="action" value="mount" />
						<input type="hidden" name="token" value="{$token}" />
						<div class="form-group">
							<label for="iso_id">Select ISO to Mount</label>
							<select name="iso_id" id="iso_id" class="form-control" required>
								<option value="">-- Select ISO --</option>
								{foreach from=$isos item=iso}
									<option value="{$iso.id|default:$iso.iso_id}"
										{if isset($mountedIsoId) && ($iso.id|default:$iso.iso_id) == $mountedIsoId}selected{/if}>
										{$iso.name|default:$iso.filename|default:'Unknown ISO'}
									</option>
								{/foreach}
							</select>
						</div>
						<button type="submit" class="btn btn-primary"
								onclick="return confirm('Mount this ISO? The server may need to be rebooted.');">
							Mount ISO
						</button>
					</form>
				{else}
					<p>No ISOs available.</p>
				{/if}
			</div>
		</div>
	</div>
</div>
