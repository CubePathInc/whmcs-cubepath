{include file="modules/servers/cubepath/template/element/flashMessages.tpl"}
{include file="modules/servers/cubepath/template/element/mainButtons.tpl"}

<div class="row">
	<div class="col-sm-12">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">SSH Keys</h3>
			</div>
			<div class="panel-body">
				{if isset($keys) && $keys|@count > 0}
					<table class="table table-bordered table-striped">
						<thead>
							<tr>
								<th>Name</th>
								<th>Fingerprint</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$keys item=key}
								<tr>
									<td>{$key.name|default:'N/A'}</td>
									<td><code>{$key.fingerprint|default:'N/A'}</code></td>
									<td>
										<form method="post" action="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Sshkeys&cloudAction=delete"
											  style="display: inline;">
											<input type="hidden" name="key_id" value="{$key.id}" />
											<input type="hidden" name="token" value="{$token}" />
											<button type="submit" class="btn btn-xs btn-danger"
													onclick="return confirm('Are you sure you want to delete this SSH key?');">
												Delete
											</button>
										</form>
									</td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				{else}
					<p>No SSH keys found.</p>
				{/if}
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-sm-8">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">Add SSH Key</h3>
			</div>
			<div class="panel-body">
				<form method="post" action="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Sshkeys&cloudAction=add">
					<input type="hidden" name="token" value="{$token}" />
					<div class="form-group">
						<label for="name">Key Name</label>
						<input type="text" name="name" id="name" class="form-control" placeholder="My SSH Key" required />
					</div>
					<div class="form-group">
						<label for="ssh_key">Public Key</label>
						<textarea name="ssh_key" id="ssh_key" class="form-control" rows="5" placeholder="ssh-rsa AAAAB3..." required></textarea>
					</div>
					<button type="submit" class="btn btn-primary">Add SSH Key</button>
				</form>
			</div>
		</div>
	</div>
</div>
