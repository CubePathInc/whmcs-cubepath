{include file="modules/servers/cubepath/template/element/flashMessages.tpl"}
{include file="modules/servers/cubepath/template/element/mainButtons.tpl"}

<div class="row">
	<div class="col-sm-12">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">DNS Zones</h3>
			</div>
			<div class="panel-body">
				{if isset($zones) && $zones|@count > 0}
					<table class="table table-bordered table-striped">
						<thead>
							<tr>
								<th>Domain</th>
								<th>Status</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$zones item=zone}
								<tr>
									<td>{$zone.domain|default:$zone._domain|default:'N/A'}</td>
									<td>
										{if isset($zone.status)}
											{if $zone.status == 'active'}
												<span class="label label-success">{$zone.status}</span>
											{elseif $zone.status == 'pending_verification'}
												<span class="label label-warning">{$zone.status}</span>
											{else}
												<span class="label label-default">{$zone.status}</span>
											{/if}
										{else}
											<span class="label label-default">unknown</span>
										{/if}
									</td>
									<td>
										<a href="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Dns&cloudAction=manage&zone_uuid={$zone._zone_uuid}"
										   class="btn btn-xs btn-primary">Manage Records</a>
										<form method="post" action="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Dns&cloudAction=delete"
											  style="display: inline;">
											<input type="hidden" name="zone_uuid" value="{$zone._zone_uuid}" />
											<input type="hidden" name="token" value="{$token}" />
											<button type="submit" class="btn btn-xs btn-danger"
													onclick="return confirm('Are you sure you want to delete this DNS zone?');">
												Delete
											</button>
										</form>
									</td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				{else}
					<p>No DNS zones found.</p>
				{/if}
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-sm-6">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">Create DNS Zone</h3>
			</div>
			<div class="panel-body">
				<form method="post" action="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Dns&cloudAction=create">
					<input type="hidden" name="token" value="{$token}" />
					<div class="form-group">
						<label for="domain">Domain Name</label>
						<input type="text" name="domain" id="domain" class="form-control" placeholder="example.com" required />
					</div>
					<button type="submit" class="btn btn-primary">Create Zone</button>
				</form>
			</div>
		</div>
	</div>
</div>
