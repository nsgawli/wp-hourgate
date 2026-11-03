import React from 'react';
import AsyncSelect from 'react-select/async';
import { Controller, useController } from 'react-hook-form';
import Flex from '@framework/components/Flex';
import Label from '@framework/components/form/Label';
import { useFieldIds } from '@framework/components/form/fieldIds';
import '../../index.css'; // form styles
import '../index.css'; // component styles

const InputSelectAsync = ({
	name,
	loadOptions,
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
	cacheOptions = true,
	defaultOptions = true,
}) => {
	const { fieldState } = useController({
		name,
		control,
	});
	const ids = useFieldIds();

	// Ensure options are in the correct format for react-select
	const setFieldValue = (field, options) => {
		const value = field.value;
		if (!options) return field;
		if (isMulti) {
			field.value = value.map(
				(item) =>
					options.find(
						(option) =>
							option.value ===
							(item && item.value !== undefined
								? item.value
								: item)
					) || item
			);
		}
		return field;
	};

	// Function to get the field value in the format expected by our application
	const getFieldValue = (value) => {
		if (isMulti) {
			return value || [];
		}

		return value || null;
	};

	className = className ? `hourgate-form-field ${className}` : 'hourgate-form-field';

	return (
		<Flex className={className} style={style} vertical gap={3}>
			{label && (
				<Label
					text={label}
					htmlFor={ids.field}
					required={!!validations.required}
				/>
			)}
			{extaInfo && <small id={ids.info}>{extaInfo}</small>}
			<Controller
				name={name}
				control={control}
				rules={validations}
				render={({ field }) => (
					<AsyncSelect
						{...setFieldValue(
							field,
							field.options || (field._f && field._f.options)
						)}
						inputId={ids.field}
						aria-invalid={fieldState.error ? true : undefined}
						aria-errormessage={fieldState.error?.message ? ids.error : undefined}
						placeholder={placeholder}
						loadOptions={loadOptions}
						isMulti={isMulti}
						isDisabled={isDisabled}
						isClearable={isClearable}
						menuPortalTarget={document.body}
						classNamePrefix="hourgate-select"
						styles={styles}
						formatOptionLabel={formatOptionLabel}
						cacheOptions={cacheOptions}
						defaultOptions={defaultOptions}
						onChange={(value) => {
							field.onChange(getFieldValue(value));
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

export default InputSelectAsync;
