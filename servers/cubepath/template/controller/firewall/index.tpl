{include file="modules/servers/cubepath/template/element/flashMessages.tpl"}
{include file="modules/servers/cubepath/template/element/mainButtons.tpl"}

<div class="row">
	<div class="col-sm-12">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">Firewall Groups</h3>
			</div>
			<div class="panel-body">
				{if isset($groups) && $groups|@count > 0}
					<form method="post" action="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Firewall&cloudAction=assign">
						<input type="hidden" name="token" value="{$token}" />
						<table class="table table-bordered table-striped">
							<thead>
								<tr>
									<th style="width: 40px;">Assign</th>
									<th>Group Name</th>
									<th>Description</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								{foreach from=$groups item=group}
									<tr>
										<td>
											<input type="checkbox" name="group_ids[]" value="{$group.id}"
												{if isset($assignedGroupIds) && in_array($group.id, $assignedGroupIds)}checked{/if} />
										</td>
										<td>{$group.name|default:'N/A'}</td>
										<td>{$group.description|default:''}</td>
										<td>
											{if isset($group.enabled) && $group.enabled}
												<span class="label label-success">Enabled</span>
											{else}
												<span class="label label-default">Disabled</span>
											{/if}
										</td>
									</tr>
								{/foreach}
							</tbody>
						</table>
						<button type="submit" class="btn btn-primary">Save Firewall Assignment</button>
					</form>
				{else}
					<p>No firewall groups available.</p>
				{/if}
			</div>
		</div>
	</div>
</div>
