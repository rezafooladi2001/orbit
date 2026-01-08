/**
 * Tests for Button Component
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { Button } from './Button';

describe('Button', () => {
  it('should render with children', () => {
    render(<Button>Click me</Button>);
    
    expect(screen.getByRole('button', { name: /click me/i })).toBeInTheDocument();
  });

  it('should apply variant classes', () => {
    const { rerender } = render(<Button variant="primary">Primary</Button>);
    expect(screen.getByRole('button')).toHaveClass('primary');

    rerender(<Button variant="secondary">Secondary</Button>);
    expect(screen.getByRole('button')).toHaveClass('secondary');

    rerender(<Button variant="danger">Danger</Button>);
    expect(screen.getByRole('button')).toHaveClass('danger');
  });

  it('should apply size classes', () => {
    const { rerender } = render(<Button size="sm">Small</Button>);
    expect(screen.getByRole('button')).toHaveClass('sm');

    rerender(<Button size="md">Medium</Button>);
    expect(screen.getByRole('button')).toHaveClass('md');

    rerender(<Button size="lg">Large</Button>);
    expect(screen.getByRole('button')).toHaveClass('lg');
  });

  it('should apply fullWidth class when prop is true', () => {
    render(<Button fullWidth>Full Width</Button>);
    
    expect(screen.getByRole('button')).toHaveClass('fullWidth');
  });

  it('should be disabled when disabled prop is true', () => {
    render(<Button disabled>Disabled</Button>);
    
    expect(screen.getByRole('button')).toBeDisabled();
  });

  it('should be disabled when loading', () => {
    render(<Button loading>Loading</Button>);
    
    expect(screen.getByRole('button')).toBeDisabled();
    expect(screen.getByRole('button')).toHaveAttribute('aria-busy', 'true');
  });

  it('should call onClick handler when clicked', () => {
    const handleClick = vi.fn();
    render(<Button onClick={handleClick}>Click me</Button>);
    
    fireEvent.click(screen.getByRole('button'));
    
    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('should not call onClick when disabled', () => {
    const handleClick = vi.fn();
    render(<Button onClick={handleClick} disabled>Click me</Button>);
    
    fireEvent.click(screen.getByRole('button'));
    
    expect(handleClick).not.toHaveBeenCalled();
  });

  it('should not call onClick when loading', () => {
    const handleClick = vi.fn();
    render(<Button onClick={handleClick} loading>Click me</Button>);
    
    fireEvent.click(screen.getByRole('button'));
    
    expect(handleClick).not.toHaveBeenCalled();
  });

  it('should trigger haptic feedback on click', () => {
    const handleClick = vi.fn();
    render(<Button onClick={handleClick} haptic>Click me</Button>);
    
    fireEvent.click(screen.getByRole('button'));
    
    expect(window.Telegram.WebApp.HapticFeedback.impactOccurred).toHaveBeenCalled();
  });

  it('should not trigger haptic feedback when haptic is false', () => {
    render(<Button haptic={false}>Click me</Button>);
    
    fireEvent.click(screen.getByRole('button'));
    
    expect(window.Telegram.WebApp.HapticFeedback.impactOccurred).not.toHaveBeenCalled();
  });

  it('should use custom aria-label when provided', () => {
    render(<Button aria-label="Custom label">Button</Button>);
    
    expect(screen.getByRole('button')).toHaveAttribute('aria-label', 'Custom label');
  });

  it('should show spinner when loading', () => {
    render(<Button loading>Loading</Button>);
    
    // Check for sr-only loading text
    expect(screen.getByText('Loading...')).toBeInTheDocument();
  });

  it('should apply custom className', () => {
    render(<Button className="custom-class">Button</Button>);
    
    expect(screen.getByRole('button')).toHaveClass('custom-class');
  });

  it('should forward additional props', () => {
    render(<Button data-testid="test-button" type="submit">Submit</Button>);
    
    expect(screen.getByTestId('test-button')).toHaveAttribute('type', 'submit');
  });
});

