import React from 'react';
import styles from './Input.module.css';

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  helperText?: string;
  rightElement?: React.ReactNode;
}

export function Input({
  label,
  error,
  helperText,
  rightElement,
  className = '',
  id,
  'aria-describedby': ariaDescribedBy,
  ...props
}: InputProps) {
  const inputId = id || `input-${Math.random().toString(36).substring(2, 9)}`;
  const errorId = error ? `${inputId}-error` : undefined;
  const helperId = helperText && !error ? `${inputId}-helper` : undefined;
  const describedBy = [ariaDescribedBy, errorId, helperId].filter(Boolean).join(' ') || undefined;

  return (
    <div className={`${styles.wrapper} ${className}`}>
      {label && (
        <label htmlFor={inputId} className={styles.label}>
          {label}
        </label>
      )}
      <div className={`${styles.inputWrapper} ${error ? styles.hasError : ''}`}>
        <input
          id={inputId}
          className={styles.input}
          aria-invalid={error ? 'true' : undefined}
          aria-describedby={describedBy}
          {...props}
        />
        {rightElement && <div className={styles.rightElement} aria-hidden="true">{rightElement}</div>}
      </div>
      {error && (
        <span id={errorId} className={styles.error} role="alert">
          {error}
        </span>
      )}
      {helperText && !error && (
        <span id={helperId} className={styles.helper}>
          {helperText}
        </span>
      )}
    </div>
  );
}

interface NumberInputProps extends Omit<InputProps, 'type' | 'onChange'> {
  value: string | number;
  onChange: (value: string) => void;
  min?: number;
  max?: number;
}

export function NumberInput({
  value,
  onChange,
  min,
  max,
  ...props
}: NumberInputProps) {
  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = e.target.value;
    
    // Allow empty string or valid number
    if (val === '' || /^\d*\.?\d*$/.test(val)) {
      onChange(val);
    }
  };

  return (
    <Input
      type="text"
      inputMode="decimal"
      value={value}
      onChange={handleChange}
      min={min}
      max={max}
      {...props}
    />
  );
}

