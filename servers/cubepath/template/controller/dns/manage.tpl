{include file="modules/servers/cubepath/template/element/flashMessages.tpl"}
{include file="modules/servers/cubepath/template/element/mainButtons.tpl"}

<div class="row">
	<div class="col-sm-12">
		<a href="clientarea.php?action=productdetails&id={$serviceid}&cloudController=Dns" class="btn btn-default" style="margin-bottom: 15px;">
			&larr; Back to DNS Zones
		</a>
	</div>
</div>

<div class="row">
	<div class="col-sm-12">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">DNS Records for {$domain}</h3>
			</div>
			<div class="panel-body">
				{if isset($records) && $records|@count > 0}
					<div class="table-responsive">
						<table class="table table-bordered table-striped">
							<thead>
								<tr>
									<th>Name</th>
									<th>Type</th>
									<th>Content</th>
									<th>TTL</th>
									<th>Priority</th>
									<th>Actions</th>
								</tr>
							</thead>
							<tbody>
								{foreach from=$records item=record}
									<tr>
										<td>{$record.name|default:''}</td>
										<td>{$record.type|default:''}</td>
										<td style="max-width: 300px; word-break: break-all;">{$record.content|default:''}</td>
										<td>{$record.ttl|default:''}</td>
										<td>{$record.priority|default:''}</td>
										<td style="white-space: nowrap;">
											<button class="btn btn-xs btn-primary" data-toggle="modal"
													data-target="#editModal_{$record.uuid|default:$record.id|default:$record@index}">
												Edit
											</button>
											<form method="post" style="display: inline;">
												<input type="hidden" name="delete_record" value="1" />
												<input type="hidden" name="record_uuid" value="{$record.uuid|default:$record.id}" />
												<input type="hidden" name="token" value="{$token}" />
												<button type="submit" class="btn btn-xs btn-danger"
														onclick="return confirm('Delete this record?');">
													Delete
												</button>
											</form>
										</td>
									</tr>

									{* Edit Modal *}
									<div class="modal fade" id="editModal_{$record.uuid|default:$record.id|default:$record@index}" tabindex="-1" role="dialog">
										<div class="modal-dialog" role="document">
											<div class="modal-content">
												<form method="post">
													<div class="modal-header">
														<button type="button" class="close" data-dismiss="modal">&times;</button>
														<h4 class="modal-title">Edit Record</h4>
													</div>
													<div class="modal-body">
														<input type="hidden" name="update_record" value="1" />
														<input type="hidden" name="record_uuid" value="{$record.uuid|default:$record.id}" />
														<input type="hidden" name="token" value="{$token}" />
														<div class="form-group">
															<label>Name</label>
															<input type="text" name="name" class="form-control" value="{$record.name|default:''}" />
														</div>
														<div class="form-group">
															<label>Type</label>
															<select name="type" class="form-control">
																{foreach from=['A','AAAA','CNAME','MX','TXT','SRV','NS','CAA'] item=rtype}
																	<option value="{$rtype}" {if $record.type == $rtype}selected{/if}>{$rtype}</option>
																{/foreach}
															</select>
														</div>
														<div class="form-group">
															<label>Content</label>
															<input type="text" name="content" class="form-control" value="{$record.content|default:''}" />
														</div>
														<div class="form-group">
															<label>TTL</label>
															<input type="number" name="ttl" class="form-control" value="{$record.ttl|default:3600}" min="60" />
														</div>
														<div class="form-group">
															<label>Priority</label>
															<input type="number" name="priority" class="form-control" value="{$record.priority|default:''}" />
														</div>
													</div>
													<div class="modal-footer">
														<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
														<button type="submit" class="btn btn-primary">Save Changes</button>
													</div>
												</form>
											</div>
										</div>
									</div>
								{/foreach}
							</tbody>
						</table>
					</div>
				{else}
					<p>No records found for this zone.</p>
				{/if}
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-sm-8">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title">Add Record</h3>
			</div>
			<div class="panel-body">
				<form method="post" class="form-horizontal">
					<input type="hidden" name="create_record" value="1" />
					<input type="hidden" name="token" value="{$token}" />
					<div class="form-group">
						<label class="col-sm-3 control-label">Name</label>
						<div class="col-sm-9">
							<input type="text" name="name" class="form-control" placeholder="e.g., www" />
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Type</label>
						<div class="col-sm-9">
							<select name="type" class="form-control">
								<option value="A">A</option>
								<option value="AAAA">AAAA</option>
								<option value="CNAME">CNAME</option>
								<option value="MX">MX</option>
								<option value="TXT">TXT</option>
								<option value="SRV">SRV</option>
								<option value="NS">NS</option>
								<option value="CAA">CAA</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Content</label>
						<div class="col-sm-9">
							<input type="text" name="content" class="form-control" placeholder="Record value" required />
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">TTL</label>
						<div class="col-sm-9">
							<input type="number" name="ttl" class="form-control" value="3600" min="60" />
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label">Priority</label>
						<div class="col-sm-9">
							<input type="number" name="priority" class="form-control" placeholder="For MX/SRV only" />
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-3 col-sm-9">
							<button type="submit" class="btn btn-primary">Add Record</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
