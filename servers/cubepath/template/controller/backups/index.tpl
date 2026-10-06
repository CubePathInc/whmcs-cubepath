{include file="modules/servers/cubepath/template/element/flashMessages.tpl"}
{include file="modules/servers/cubepath/template/element/mainButtons.tpl"}

<div class="row">
	<div class="col-sm-12">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">Backups</h3>
			</div>
			<div class="panel-body">
				{if isset($backups) && $backups|@count > 0}
					<table class="table table-bordered table-striped">
						<thead>
							<tr>
								<th>ID</th>
								<th>Date</th>
								<th>Size</th>
								<th>Status</th>
								<th>Notes</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$backups item=backup}
								<tr>
									<td>{$backup.id|default:$backup.backup_id|default:'N/A'}</td>
									<td>{$backup.created_at|default:$backup.date|default:'N/A'}</td>
									<td>{$backup.size|default:'N/A'}</td>
									<td>
										{if $backup.status == 'completed' || $backup.status == 'complete'}
											<span class="label label-success">{$backup.status}</span>
										{elseif $backup.status == 'in_progress' || $backup.status == 'pending'}
											<span class="label label-info">{$backup.status}</span>
										{else}
											<span class="label label-default">{$backup.status|default:'unknown'}</span>
										{/if}
									</td>
									<td>{$backup.notes|default:''}</td>
									<td>
										<form method="post" action="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Backups&cloudAction=restore" style="display: inline;">
											<input type="hidden" name="backup_id" value="{$backup.id|default:$backup.backup_id}" />
											<input type="hidden" name="token" value="{$token}" />
											<button type="submit" class="btn btn-xs btn-warning"
													onclick="return confirm('Are you sure you want to restore from this backup? This will overwrite all current data.');">
												Restore
											</button>
										</form>
										<form method="post" action="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Backups&cloudAction=delete" style="display: inline;">
											<input type="hidden" name="backup_id" value="{$backup.id|default:$backup.backup_id}" />
											<input type="hidden" name="token" value="{$token}" />
											<button type="submit" class="btn btn-xs btn-danger"
													onclick="return confirm('Are you sure you want to delete this backup?');">
												Delete
											</button>
										</form>
									</td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				{else}
					<p>No backups found.</p>
				{/if}
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-sm-6">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">Create Backup</h3>
			</div>
			<div class="panel-body">
				<form method="post" action="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Backups&cloudAction=create">
					<input type="hidden" name="token" value="{$token}" />
					<div class="form-group">
						<label for="notes">Notes (optional)</label>
						<input type="text" name="notes" id="notes" class="form-control" placeholder="Backup description" />
					</div>
					<button type="submit" class="btn btn-primary">Create Backup</button>
				</form>
			</div>
		</div>
	</div>
	<div class="col-sm-6">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">Backup Settings</h3>
			</div>
			<div class="panel-body">
				<form method="post" action="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Backups&cloudAction=settings">
					<input type="hidden" name="token" value="{$token}" />
					<div class="form-group">
						<label>
							<input type="checkbox" name="enabled" value="1"
								{if isset($settings.enabled) && $settings.enabled}checked{/if} />
							Enable automatic backups
						</label>
					</div>
					<div class="form-group">
						<label for="schedule_hour">Schedule Hour (0-23)</label>
						<input type="number" name="schedule_hour" id="schedule_hour" class="form-control"
							   min="0" max="23" value="{$settings.schedule_hour|default:0}" />
					</div>
					<div class="form-group">
						<label for="retention_days">Retention Days (1-7)</label>
						<input type="number" name="retention_days" id="retention_days" class="form-control"
							   min="1" max="7" value="{$settings.retention_days|default:1}" />
					</div>
					<div class="form-group">
						<label for="max_backups">Max Backups (1-10)</label>
						<input type="number" name="max_backups" id="max_backups" class="form-control"
							   min="1" max="10" value="{$settings.max_backups|default:1}" />
					</div>
					<button type="submit" class="btn btn-primary">Save Settings</button>
				</form>
			</div>
		</div>
	</div>
</div>
