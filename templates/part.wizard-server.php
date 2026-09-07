<div class="outoftheway">
	<!-- Hack for Safari and Chromium/Chrome which ignore autocomplete="off" -->
	<!-- Köderfelder gegen das automatische Ausfüllen: Sie haben für Menschen keine
		 Bedeutung und werden deshalb nicht beschriftet, sondern mit aria-hidden aus
		 dem Barrierefreiheitsbaum und mit tabindex="-1" aus der Tabreihenfolge
		 genommen. Für den Browser sichtbar bleiben sie, sonst greift der Köder nicht. -->
	<input type="text" id="fake_user" name="fake_user" autocomplete="off"
				aria-hidden="true" tabindex="-1" />
	<input type="password" id="fake_password" name="fake_password"
				autocomplete="off" aria-hidden="true" tabindex="-1" />
</div>
<fieldset id="ldapWizard1">
	<section>
		<div class="tablerow">
			<div class="inline">
				<div>
					<!-- Das Auswahlfeld hat keine sichtbare Beschriftung; der Name kommt aus der
						 bereits vorhandenen Zeichenkette der Registerkarte "Server". -->
					<select id="ldap_serverconfig_chooser" name="ldap_serverconfig_chooser"
						aria-label="<?php p($l->t('Server')); ?>">
					<?php if (\count($_['serverConfigurationPrefixes']) === 0) {
						?>
						<option value="" selected><?php p($l->t('1. Server')); ?></option>');
					<?php
					} else {
						$i = 1;
						$sel = ' selected';
						foreach ($_['serverConfigurationPrefixes'] as $prefix) {
							?>
								<option value="<?php p($prefix); ?>"<?php p($sel);
							$sel = ''; ?>><?php p($l->t('%s. Server:', [$i++])); ?> <?php p(' '.$_['serverConfigurationHosts'][$prefix]); ?></option>
							<?php
						}
					}
					?>
					</select>
				</div>

				<div>
					<!-- Die drei Schaltflächen zeigen nur ein Symbol; ihr Name stammte bisher
						 allein aus title. Der Tooltip des Kerns verschiebt title beim Start nach
						 data-original-title und leert das Attribut, womit der Name verschwindet.
						 aria-label hält denselben, bereits vorhandenen Text dauerhaft fest. -->
					<button type="button" id="ldap_action_add_configuration"
						name="ldap_action_add_configuration" class="icon-add icon-default-style"
						aria-label="<?php p($l->t('Add a new and blank configuration')); ?>"
						title="<?php p($l->t('Add a new and blank configuration')); ?>">&nbsp;</button>
					<button type="button" id="ldap_action_copy_configuration"
						name="ldap_action_copy_configuration"
						class="ldapIconCopy icon-default-style"
						aria-label="<?php p($l->t('Copy current configuration into new directory binding')); ?>"
						title="<?php p($l->t('Copy current configuration into new directory binding')); ?>">&nbsp;</button>
					<button type="button" id="ldap_action_delete_configuration"
					name="ldap_action_delete_configuration" class="icon-delete icon-default-style"
					aria-label="<?php p($l->t('Delete the current configuration')); ?>"
					title="<?php p($l->t('Delete the current configuration')); ?>">&nbsp;</button>
				</div>
			</div>
		</div>

		<div class="tablerow">
			<div class="inline">
				<div>
					<label for="ldap_host"><?php p($l->t('Host')); ?></label>
					<input type="text" class="host" id="ldap_host" name="ldap_host" />
				</div>

				<div>
					<label for="ldap_port"><?php p($l->t('Port')); ?></label>
					<input type="number" id="ldap_port" name="ldap_port" />
				</div>
			</div>
			<div class="hint">
				<?php p($l->t('You can omit the protocol, except you require SSL. Then start with ldaps://')); ?>
			</div>
		</div>

		<div class="tablerow">
			<label for="ldap_tls"><?php p($l->t('Use StartTLS support')); ?></label>
			<input type="checkbox" id="ldap_tls" name="ldap_tls" value="1">
			<div class="hint">
				<?php p($l->t('Enable StartTLS support (also known as LDAP over TLS) for the connection.  Note that this is different than LDAPS (LDAP over SSL) which doesn\'t need this checkbox checked. You\'ll need to import the LDAP server\'s certificate in your %s server.', $theme->getName())); ?>
			</div>
		</div>

		<div class="tablerow">
			<label for="ldap_dn"><?php p($l->t('User DN')); ?></label>
			<input type="text" id="ldap_dn" name="ldap_dn" class="tablecell" autocomplete="off" />
			<div class="hint">
				<?php p($l->t('The DN of the client user with which the bind shall be done, e.g. uid=agent,dc=example,dc=com. For anonymous access, leave DN and Password empty.')); ?>
			</div>
		</div>

		<div class="tablerow">
			<label for="ldap_agent_password"><?php p($l->t('Password')); ?></label>
			<input type="password" id="ldap_agent_password" class="tablecell" name="ldap_agent_password" autocomplete="off" />
			<div class="hint">
				<?php p($l->t('For anonymous access, leave DN and Password empty.')); ?>
			</div>
		</div>

		<div class="tablerow">
			<label for="ldap_base"><?php p($l->t('One Base DN per line')); ?></label>
			<textarea id="ldap_base" name="ldap_base" class="tablecell" placeholder="" >
			</textarea>
			<div class="hint">
				<?php p($l->t('You can specify Base DN for users and groups in the Advanced tab')); ?>
			</div>
		</div>

		<div class="tablerow">
			<button class="ldapDetectBase" name="ldapDetectBase" type="button"><?php p($l->t('Detect Base DN')); ?></button>
			<button class="ldapTestBase" name="ldapTestBase" type="button"><?php p($l->t('Test Base DN')); ?></button>
			<div class="resultscontainer" id="ldapTestBaseResult"><span></span></div>
		</div>

		<div class="tablerow">
			<label for="ldap_experienced_admin" class="tablecell">
				<?php p($l->t('Manually enter LDAP filters (recommended for large directories)')); ?>
			</label>
			<input type="checkbox" id="ldap_experienced_admin" value="1" name="ldap_experienced_admin" class="tablecell" />
			<div class="hint">
				<?php p($l->t('Avoids automatic LDAP requests. Better for bigger setups, but requires some LDAP knowledge.')); ?>
			</div>
		</div>
	</section>

	<section>
		<?php print_unescaped($_['wizardControls']); ?>

		<div class="tablerow">
			<div class="tablecell ldapWizardInfo invisible">&nbsp;
			</div>
		</div>
	</section>
</fieldset>
