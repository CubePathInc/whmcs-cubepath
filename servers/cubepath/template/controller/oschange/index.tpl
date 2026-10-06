{include file="modules/servers/cubepath/template/element/flashMessages.tpl"}
{include file="modules/servers/cubepath/template/element/mainButtons.tpl"}

<div class="row">
	<div class="col-sm-12">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">Reinstall Operating System</h3>
			</div>
			<div class="panel-body">
				<div class="alert alert-warning">
					<strong>Warning:</strong> Reinstalling the OS will erase all data on the server.
					Make sure you have backups before proceeding.
				</div>

				{if isset($vps)}
					<p><strong>Current OS:</strong> {$vps.template|default:$vps.template_name|default:'N/A'}</p>
				{/if}

				{if isset($templates)}
					<form method="post">
						<input type="hidden" name="token" value="{$token}" />
						<div class="form-group">
							<label for="template_name">Select OS Template</label>
							<select name="template_name" id="template_name" class="form-control" required>
								<option value="">-- Select Template --</option>
								{if isset($templates.operating_systems) && $templates.operating_systems|@count > 0}
									<optgroup label="Operating Systems">
										{foreach from=$templates.operating_systems item=os}
											<option value="{$os.name|default:$os.template_name}">{$os.name|default:$os.template_name|default:'Unknown'}</option>
										{/foreach}
									</optgroup>
								{/if}
								{if isset($templates.applications) && $templates.applications|@count > 0}
									<optgroup label="Applications">
										{foreach from=$templates.applications item=app}
											<option value="{$app.name|default:$app.template_name}">{$app.name|default:$app.template_name|default:'Unknown'}</option>
										{/foreach}
									</optgroup>
								{/if}
								{* If templates is a flat array *}
								{if !isset($templates.operating_systems) && !isset($templates.applications)}
									{foreach from=$templates item=tmpl}
										{if is_array($tmpl) && isset($tmpl.name)}
											<option value="{$tmpl.name|default:$tmpl.template_name}">{$tmpl.name|default:$tmpl.template_name|default:'Unknown'}</option>
										{/if}
									{/foreach}
								{/if}
							</select>
						</div>
						<button type="submit" class="btn btn-danger"
								onclick="return confirm('WARNING: This will completely erase all data on the server and reinstall the operating system. Are you absolutely sure?');">
							Reinstall OS
						</button>
					</form>
				{else}
					<p>No templates available.</p>
				{/if}
			</div>
		</div>
	</div>
</div>
