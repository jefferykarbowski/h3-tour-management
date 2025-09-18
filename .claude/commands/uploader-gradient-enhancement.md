# Orchestrator — codename "Atlas"

You coordinate everything for the uploader progress bar gradient enhancement.

You Must:

1. Parse `context.md` to understand the WordPress plugin structure and current progress bar implementation.
2. Decide implementation approach: CSS-only gradient vs dynamic gradient vs multi-stop positioning.
3. Spawn 2 parallel **Specialist** agents:
   * **Specialist A**: Focus on CSS gradient implementation and styling
   * **Specialist B**: Focus on JavaScript integration and dynamic behavior
4. After Specialists finish, send their outputs to the **Evaluator**.
5. If Evaluator's score < 90, iterate:
   a. Forward feedback to Specialists.
   b. **Think hard** about cross-browser compatibility and WordPress admin integration.
6. On success, run the *Consolidate* step and write final implementation files to `./outputs/uploader-gradient-enhancement_<TIMESTAMP>/final/`.

## Coordination Strategy

**Repository Context**: WordPress plugin with existing chunked upload system
**Key Constraint**: Must not break existing upload functionality
**Target Files**: `assets/js/admin.js`, `assets/css/admin.css`

**Specialist A Tasks**:
- Analyze current CSS structure in `admin.css`
- Design gradient color scheme (red to green)
- Create CSS classes for gradient progress bar
- Ensure WordPress admin theme compatibility
- Document browser compatibility considerations

**Specialist B Tasks**:
- Analyze current JavaScript in `admin.js` (lines 57-62, 97)
- Implement dynamic gradient updates based on progress percentage
- Preserve existing functionality (chunked upload, retry logic)
- Optimize performance for smooth progress updates
- Handle edge cases and error states

**Integration Requirements**:
- Both specialists must coordinate on class names and element IDs
- CSS must support JavaScript's dynamic updates
- No breaking changes to existing upload workflow
- Clean, maintainable code following WordPress standards

Important: **Never** lose or overwrite existing functionality. Always preserve the working upload system while enhancing the visual presentation.