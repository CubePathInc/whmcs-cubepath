{include file="modules/servers/cubepath/template/element/flashMessages.tpl"}
{include file="modules/servers/cubepath/template/element/mainButtons.tpl"}

{if isset($vps)}
<div class="row">
	<div class="col-sm-12">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">Server Overview</h3>
			</div>
			<div class="panel-body">
				<div class="row" style="margin-bottom: 15px;">
					<div class="col-sm-12">
						<form method="post" style="display: inline-block; margin-right: 5px;">
							<input type="hidden" name="action" value="start" />
							<input type="hidden" name="token" value="{$token}" />
							<button type="submit" class="btn btn-success">
								<span class="glyphicon glyphicon-play"></span> Start
							</button>
						</form>
						<form method="post" style="display: inline-block; margin-right: 5px;">
							<input type="hidden" name="action" value="stop" />
							<input type="hidden" name="token" value="{$token}" />
							<button type="submit" class="btn btn-danger"
									onclick="return confirm('Are you sure you want to stop this server?');">
								<span class="glyphicon glyphicon-stop"></span> Stop
							</button>
						</form>
						<form method="post" style="display: inline-block; margin-right: 5px;">
							<input type="hidden" name="action" value="reboot" />
							<input type="hidden" name="token" value="{$token}" />
							<button type="submit" class="btn btn-warning"
									onclick="return confirm('Are you sure you want to reboot this server?');">
								<span class="glyphicon glyphicon-refresh"></span> Reboot
							</button>
						</form>
					</div>
				</div>

				<table class="table table-bordered table-striped">
					<tr>
						<td style="width: 200px;"><strong>Hostname</strong></td>
						<td>{$vps.name|default:'N/A'}</td>
					</tr>
					<tr>
						<td><strong>Label</strong></td>
						<td>
							<span id="cp_label_display">
								{$vps.label|default:'N/A'}
								<button class="btn btn-xs btn-default" onclick="$('#cp_label_display').hide(); $('#cp_label_form').show();">
									Change
								</button>
							</span>
							<form method="post" id="cp_label_form" class="form-inline" style="display: none;">
								<input type="hidden" name="action" value="update_label" />
								<input type="hidden" name="token" value="{$token}" />
								<input type="text" name="label" class="form-control input-sm" value="{$vps.label|default:''}" maxlength="250" />
								<button type="submit" class="btn btn-sm btn-primary">Save</button>
								<button type="button" class="btn btn-sm btn-default"
										onclick="$('#cp_label_form').hide(); $('#cp_label_display').show();">
									Cancel
								</button>
							</form>
						</td>
					</tr>
					<tr>
						<td><strong>Status</strong></td>
						<td>
							{if $vps.status == 'active'}
								<span class="label label-success">{$vps.status}</span>
							{elseif $vps.status == 'installing' || $vps.status == 'pending'}
								<span class="label label-info">{$vps.status}</span>
							{else}
								<span class="label label-default">{$vps.status|default:'unknown'}</span>
							{/if}
						</td>
					</tr>
					<tr>
						<td><strong>Power Status</strong></td>
						<td>
							{if $vps.power_status == 'running'}
								<span style="color: green; font-weight: bold;">{$vps.power_status}</span>
							{else}
								<span style="color: red; font-weight: bold;">{$vps.power_status|default:'unknown'}</span>
							{/if}
						</td>
					</tr>
					<tr>
						<td><strong>Plan</strong></td>
						<td>{$vps.plan|default:$vps.plan_name|default:'N/A'}</td>
					</tr>
					<tr>
						<td><strong>Location</strong></td>
						<td>{$vps.location|default:$vps.location_name|default:'N/A'}</td>
					</tr>
					<tr>
						<td><strong>OS Template</strong></td>
						<td>{$vps.template|default:$vps.template_name|default:'N/A'}</td>
					</tr>
					<tr>
						<td><strong>IP Address</strong></td>
						<td>
							{if isset($vps.ip_address)}
								{$vps.ip_address}
							{elseif isset($vps.main_ip)}
								{$vps.main_ip}
							{else}
								N/A
							{/if}
							{if isset($vps.ipv6_address) && $vps.ipv6_address}
								<br />{$vps.ipv6_address}
							{/if}
						</td>
					</tr>
					{if isset($vps.bandwidth_used) || isset($vps.bandwidth_allowed)}
					<tr>
						<td><strong>Bandwidth</strong></td>
						<td>
							{if isset($vps.bandwidth_used) && isset($vps.bandwidth_allowed) && $vps.bandwidth_allowed > 0}
								{assign var="bw_pct" value=($vps.bandwidth_used / $vps.bandwidth_allowed * 100)|round:1}
								<div class="progress" style="margin-bottom: 0;">
									<div class="progress-bar" role="progressbar"
										 aria-valuenow="{$bw_pct}" aria-valuemin="0" aria-valuemax="100"
										 style="min-width: 2em; width: {$bw_pct}%;">
										{$vps.bandwidth_used} / {$vps.bandwidth_allowed} GB
									</div>
								</div>
							{else}
								{$vps.bandwidth_used|default:'0'} GB used
							{/if}
						</td>
					</tr>
					{/if}
				</table>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-sm-12">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">Change Password</h3>
			</div>
			<div class="panel-body">
				<form method="post" class="form-inline">
					<input type="hidden" name="action" value="change_password" />
					<input type="hidden" name="token" value="{$token}" />
					<div class="form-group">
						<input type="password" name="password" class="form-control" placeholder="New password (min 8 characters)" minlength="8" required />
					</div>
					<button type="submit" class="btn btn-primary"
							onclick="return confirm('Are you sure you want to change the root password?');">
						Change Password
					</button>
				</form>
			</div>
		</div>
	</div>
</div>
{/if}
