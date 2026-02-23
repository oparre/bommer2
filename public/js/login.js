/**
 * Login Form Client-Side Validation
 * 
 * Validates username and password before form submission
 */

(function() {
    'use strict';
    
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('loginForm');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const usernameError = document.getElementById('username-help');
        const passwordError = document.getElementById('password-help');
        
        if (!loginForm) return;
        
        /**
         * Validate username format
         * 3-50 characters, alphanumeric and underscore only
         */
        function validateUsername() {
            const username = usernameInput.value.trim();
            const usernameRegex = /^[a-zA-Z0-9_]{3,50}$/;
            
            // Clear previous error
            clearError(usernameInput, usernameError);
            
            if (username === '') {
                showError(usernameInput, usernameError, 'Username is required');
                return false;
            }
            
            if (!usernameRegex.test(username)) {
                showError(
                    usernameInput, 
                    usernameError, 
                    'Username must be 3-50 characters (letters, numbers, underscore only)'
                );
                return false;
            }
            
            return true;
        }
        
        /**
         * Validate password
         */
        function validatePassword() {
            const password = passwordInput.value;
            
            // Clear previous error
            clearError(passwordInput, passwordError);
            
            if (password === '') {
                showError(passwordInput, passwordError, 'Password is required');
                return false;
            }
            
            if (password.length < 8) {
                showError(passwordInput, passwordError, 'Password must be at least 8 characters');
                return false;
            }
            
            return true;
        }
        
        /**
         * Show validation error
         */
        function showError(input, errorElement, message) {
            input.parentElement.parentElement.classList.add('clr-error');
            errorElement.textContent = message;
            input.setAttribute('aria-invalid', 'true');
        }
        
        /**
         * Clear validation error
         */
        function clearError(input, errorElement) {
            input.parentElement.parentElement.classList.remove('clr-error');
            errorElement.textContent = '';
            input.removeAttribute('aria-invalid');
        }
        
        /**
         * Real-time validation on input
         */
        usernameInput.addEventListener('blur', validateUsername);
        passwordInput.addEventListener('blur', validatePassword);
        
        /**
         * Clear errors on focus
         */
        usernameInput.addEventListener('focus', function() {
            clearError(usernameInput, usernameError);
        });
        
        passwordInput.addEventListener('focus', function() {
            clearError(passwordInput, passwordError);
        });
        
        /**
         * Form submission validation
         */
        loginForm.addEventListener('submit', function(e) {
            // Validate all fields
            const isUsernameValid = validateUsername();
            const isPasswordValid = validatePassword();
            
            // Prevent submission if any validation fails
            if (!isUsernameValid || !isPasswordValid) {
                e.preventDefault();
                
                // Focus on first invalid field
                if (!isUsernameValid) {
                    usernameInput.focus();
                } else if (!isPasswordValid) {
                    passwordInput.focus();
                }
                
                return false;
            }
            
            // Disable submit button to prevent double submission
            const submitButton = loginForm.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Signing In...';
            }
            
            return true;
        });
        
        /**
         * Prevent spaces in username
         */
        usernameInput.addEventListener('keypress', function(e) {
            if (e.key === ' ') {
                e.preventDefault();
                return false;
            }
        });
        
        /**
         * Auto-trim username on paste
         */
        usernameInput.addEventListener('paste', function(e) {
            setTimeout(function() {
                usernameInput.value = usernameInput.value.trim();
            }, 10);
        });
    });
})();
