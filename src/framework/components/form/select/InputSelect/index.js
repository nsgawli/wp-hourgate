import React from 'react';
import Select from 'react-select';
import { Controller, useController } from 'react-hook-form';
import Flex from '@framework/components/Flex';
import Label from '@framework/components/form/Label';
import { useFieldIds, fieldAria } from '@framework/components/form/fieldIds';
import '../../index.css'; // form styles
import '../index.css'; // component styles

const InputSelect = ({
	name,
	options,
	control,
	placeholder = '',
	label = null,
	validations = {},
	extaInfo = '',
	isMulti = false,
	isDisabled = false,
	isClearable = true,
	className = '',
	style = {},
	styles = {}, // react-select styles
	formatOptionLabel = (e) => e.label, // react-select formatOptionLabel. We can override this function if needed
	onChange = null, // extra side-effect callback, fired with the same normalized value as field.onChange (e.g. resetting other fields when a "type" selector changes)
}) => {
	const { fieldState } = useController({
		name,
		control,
	});
	const ids = useFieldIds();

	// Ensure options are in the correct format for react-select
	const setFieldValue = (field) => {
		const value = field.value;
		if (isMulti) {
			field.value = Array.isArray(value)
				? value.map((item) =>
						item?.label
							? item
							: options.find((option) => option.value === item)
					)
				: [];
		} else {
			field.value = value?.label
				? value
				: options.find((option) => option.value === value);
		}
		return field;
	};

	// Function to get the field value in the format expected by our application
	const getFieldValue = (value) => {
		if (value == null) return isMulti ? [] : '';
		if (isMulti) {
			return value.map((item) => item.value);
		} else {
			return value.value;
		}
	};

	className = className ? `hourgate-form-field ${className}` : 'hourgate-form-field';

	return (
		<Flex className={className} style={style} vertical gap={3}>
			{label && (
				<Label
					text={label}
					htmlFor={ids.field}
					required={validations.required ? true : false}
				/>
			)}
			{extaInfo && <small id={ids.info}>{extaInfo}</small>}
			<Controller
				name={name}
				control={control}
				rules={validations}
				render={({ field }) => (
					<Select
						{...setFieldValue(field)}
						inputId={ids.field}
						aria-invalid={fieldState.error ? true : undefined}
						aria-errormessage={fieldState.error?.message ? ids.error : undefined}
						placeholder={placeholder}
						options={options}
						isMulti={isMulti}
						isDisabled={isDisabled}
						isClearable={isClearable}
						menuPortalTarget={document.body}
						classNamePrefix="hourgate-select"
						styles={styles}
						formatOptionLabel={formatOptionLabel}
						onChange={(value) => {
							const rawValue = getFieldValue(value);
							field.onChange(rawValue);
							if (onChange) {
								onChange(rawValue);
							}
						}}
					/>
				)}
			/>
			{fieldState.error && fieldState.error.message && (
				<small id={ids.error} className="hourgate-form-field-error-msg">
					{fieldState.error.message}
				</small>
			)}
		</Flex>
	);
};

export default InputSelect;
