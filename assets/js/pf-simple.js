/**
 * PromptFinder - Simple Variable System
 * NO WordPress complexity - just works!
 */

console.log('PF Simple loaded!');

// Global variable store
const PF_VARS = {};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('PF Simple: DOM ready');
    initVariableSystem();
});

function initVariableSystem() {
    console.log('PF Simple: Initializing variable system');
    
    // Find all variable inputs
    const variableInputs = document.querySelectorAll('input[data-var-name]');
    console.log('PF Simple: Found', variableInputs.length, 'variable inputs');
    
    // Add event listeners to all variable inputs
    variableInputs.forEach(function(input) {
        const varName = input.getAttribute('data-var-name');
        console.log('PF Simple: Setting up input for variable:', varName);
        
        // Store initial value
        if (input.value) {
            PF_VARS[varName] = input.value;
        }
        
        // Add input event listener
        input.addEventListener('input', function() {
            console.log('PF Simple: Variable changed:', varName, '=', input.value);
            PF_VARS[varName] = input.value;
            updateAllPrompts();
        });
        
        // Add change event listener (for dropdowns, etc.)
        input.addEventListener('change', function() {
            console.log('PF Simple: Variable changed (change):', varName, '=', input.value);
            PF_VARS[varName] = input.value;
            updateAllPrompts();
        });
    });
    
    // Initial update
    updateAllPrompts();
}

function updateAllPrompts() {
    console.log('PF Simple: Updating all prompts with vars:', PF_VARS);
    
    // Find all prompt textareas
    const promptTextareas = document.querySelectorAll('textarea[data-prompt-template]');
    console.log('PF Simple: Found', promptTextareas.length, 'prompt textareas');
    
    promptTextareas.forEach(function(textarea) {
        const baseTemplate = textarea.getAttribute('data-base');
        if (!baseTemplate) {
            console.log('PF Simple: No data-base found for textarea');
            return;
        }
        
        console.log('PF Simple: Updating prompt with template:', baseTemplate);
        
        // Replace variables in template
        let updatedPrompt = baseTemplate;
        
        // Find all {variable} patterns
        const variablePattern = /\{([^}]+)\}/g;
        let match;
        
        while ((match = variablePattern.exec(baseTemplate)) !== null) {
            const varName = match[1];
            const varValue = PF_VARS[varName] || '';
            console.log('PF Simple: Replacing', varName, 'with', varValue);
            updatedPrompt = updatedPrompt.replace('{' + varName + '}', varValue);
        }
        
        // Update textarea value
        textarea.value = updatedPrompt;
        console.log('PF Simple: Updated prompt:', updatedPrompt);
    });
}

// Copy to clipboard function
function copyToClipboard(textarea) {
    textarea.select();
    document.execCommand('copy');
    
    // Show feedback
    const button = textarea.nextElementSibling;
    if (button && button.classList.contains('pf-copy-btn')) {
        const originalText = button.textContent;
        button.textContent = 'Copied!';
        button.style.backgroundColor = '#28a745';
        
        setTimeout(function() {
            button.textContent = originalText;
            button.style.backgroundColor = '';
        }, 2000);
    }
}

// Make copyToClipboard globally available
window.copyToClipboard = copyToClipboard;

console.log('PF Simple: Script loaded successfully');
