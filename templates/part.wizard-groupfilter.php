<fieldset id="ldapWizard4">
	<section>
		<p>
			<?php p($l->t('Groups meeting these criteria are available in %s:', $theme->getName()));?>
		</p>
		<p>
			<label for="ldap_groupfilter_objectclass">
				<?php p($l->t('Only these object classes:'));?>
			</label>

			<select id="ldap_groupfilter_objectclass" multiple="multiple"
			 name="ldap_groupfilter_objectclass" class="multiSelectPlugin">
			</select>
		</p>
		<p>
			<label for="ldap_groupfilter_groups">
				<?php p($l->t('Only from these groups:'));?>
			</label>

			<input type="text" class="ldapManyGroupsSupport ldapManyGroupsSearch hidden" placeholder="<?php p($l->t('Search groups'));?>" />

			<select id="ldap_groupfilter_groups" multiple="multiple"
			 name="ldap_groupfilter_groups" class="multiSelectPlugin">
			</select>

		</p>
		<p class="ldapManyGroupsSupport hidden">
			<select class="ldapGroupList ldapGroupListAvailable" multiple="multiple"
					title="<?php p($l->t('Available groups'));?>"></select>
			<span class="buttonSpan">
				<button class="ldapGroupListSelect" type="button" aria-label="<?php p($l->t('Select group(s)'));?>">&gt;</button><br/>
				<button class="ldapGroupListDeselect" type="button" aria-label="<?php p($l->t('Deselect group(s)'));?>">&lt;</button>
			</span>
			<select class="ldapGroupList ldapGroupListSelected" multiple="multiple"
					title="<?php p($l->t('Selected groups'));?>"></select>
		</p>
		<p>
			<label><button type="button" id='toggleRawGroupFilter' class='ldapToggle'>↓ <?php p($l->t('Edit LDAP Query'));?></button></label>
		</p>
		<p id="ldapReadOnlyGroupFilterContainer" class="hidden ldapReadOnlyFilterContainer">
			<label><?php p($l->t('LDAP Filter:'));?></label>
			<span class="ldapFilterReadOnlyElement ldapInputColElement"></span>
		</p>
		<p id="rawGroupFilterContainer" class="invisible">
			<textarea type="text" id="ldap_group_filter" name="ldap_group_filter"
					  placeholder="<?php p($l->t('Edit LDAP Query'));?>"
					  title="<?php p($l->t('The filter specifies which LDAP groups shall have access to the %s instance.', $theme->getName()));?>">
			</textarea>
		</p>
		<p>
			<div class="ldapWizardInfo invisible">&nbsp;</div>
		</p>
		<p class="ldap_count">
			<button class="ldapGetEntryCount ldapGetGroupCount" name="ldapGetEntryCount" type="button">
				<?php p($l->t('Verify settings and count groups'));?>
			</button>
			<span id="ldap_group_count"></span>
		</p>
		<?php print_unescaped($_['wizardControls']); ?>
	</section>
</fieldset>
