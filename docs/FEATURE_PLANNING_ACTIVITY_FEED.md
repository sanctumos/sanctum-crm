# Sanctum CRM - Activity Feed & Contact Claiming Feature Plan

## 📋 Overview

This document outlines the implementation plan for two new features:
1. **Activity Feed System** - A robust activity tracking system for contact interactions
2. **Contact Claiming System** - A mechanism to flag and claim uncontacted leads

**Note**: This feature plan is for future implementation. The current v2.0.0 release focuses on first boot configuration, MCP compatibility, and comprehensive testing.

## ✅ Completed Features (v2.0.0)

### First Boot Configuration System
- ✅ **Intelligent Installation Wizard** - 5-step guided setup process
- ✅ **Environment Detection** - Automatic server environment analysis and validation
- ✅ **Dynamic Configuration Management** - Database-driven settings with encryption support
- ✅ **Company Information Setup** - Streamlined company configuration
- ✅ **Admin User Creation** - Secure administrator account setup with API key generation

### AI Agent Integration
- ✅ **MCP Compatibility** - Full Model Context Protocol support for Letta AI
- ✅ **API-First Design** - RESTful API optimized for AI agent integration
- ✅ **Configuration API** - Dynamic settings management via API endpoints
- ✅ **Installation Status API** - Real-time installation progress tracking

### Comprehensive Testing
- ✅ **100% Test Coverage** - Complete test suite with unit, integration, E2E, and API tests
- ✅ **Mock Test Framework** - HTTP-independent testing for reliable CI/CD
- ✅ **Database Cleanup** - Proper test isolation and data management
- ✅ **Configuration Testing** - Extensive testing of dynamic configuration system

## 🎯 Feature Requirements

### 1. Activity Feed System
- Track who contacted a contact, how they contacted them, and when
- Store memo/notes about the interaction
- Provide a chronological feed of all activities per contact
- Support multiple contact methods (email, phone, social media, etc.)
- Allow filtering and searching of activities

### 2. Contact Claiming System
- Automatically flag new contacts as "not contacted"
- Allow users to claim uncontacted leads
- Prevent duplicate claiming
- Track who claimed which contact and when
- Provide dashboard views for unclaimed contacts

## 🏗️ Architecture Design

### Database Schema Changes

#### New Table: `contact_activities`
```sql
CREATE TABLE contact_activities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contact_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    activity_type VARCHAR(50) NOT NULL, -- 'email', 'phone', 'social', 'meeting', etc.
    contact_method VARCHAR(50) NOT NULL, -- 'email', 'phone', 'twitter', 'linkedin', etc.
    subject VARCHAR(200),
    memo TEXT,
    activity_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

#### New Table: `contact_claims`
```sql
CREATE TABLE contact_claims (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contact_id INTEGER NOT NULL UNIQUE,
    claimed_by INTEGER NOT NULL,
    claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'released', 'transferred'
    notes TEXT,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (claimed_by) REFERENCES users(id)
);
```

#### Modified Table: `contacts`
Add new field to existing contacts table:
```sql
ALTER TABLE contacts ADD COLUMN contacted_status VARCHAR(20) DEFAULT 'not_contacted';
-- Values: 'not_contacted', 'contacted', 'claimed'
```

### API Endpoints Design

#### Activity Feed API

**GET /api/v1/contacts/{id}/activities**
- List all activities for a specific contact
- Query parameters: `limit`, `offset`, `type`, `method`, `date_from`, `date_to`

**POST /api/v1/contacts/{id}/activities**
- Create new activity record
- Required fields: `activity_type`, `contact_method`, `memo`
- Optional fields: `subject`, `activity_date`

**GET /api/v1/activities**
- List all activities across contacts
- Query parameters: `contact_id`, `user_id`, `type`, `method`, `date_from`, `date_to`, `limit`, `offset`

**PUT /api/v1/activities/{id}**
- Update activity record
- Only creator can update

**DELETE /api/v1/activities/{id}**
- Delete activity record
- Only creator or admin can delete

#### Contact Claiming API

**GET /api/v1/contacts/unclaimed**
- List all unclaimed contacts
- Query parameters: `limit`, `offset`, `source`, `created_after`

**POST /api/v1/contacts/{id}/claim**
- Claim a contact
- Returns claim details

**PUT /api/v1/contacts/{id}/release**
- Release a claimed contact
- Only claim owner can release

**PUT /api/v1/contacts/{id}/transfer**
- Transfer claim to another user
- Required field: `new_user_id`
- Only claim owner or admin can transfer

**GET /api/v1/contacts/claimed**
- List contacts claimed by current user
- Query parameters: `limit`, `offset`, `status`

### Frontend Implementation

#### Activity Feed Components

1. **Activity Feed Widget** (`/pages/view_contact.php`)
   - Display chronological list of activities
   - Add new activity form
   - Filter and search capabilities

2. **Activity Creation Modal**
   - Form for adding new activities
   - Contact method selection
   - Memo field with rich text support

3. **Activity List Component**
   - Paginated list of activities
   - Activity type icons
   - User avatars and timestamps

#### Contact Claiming Components

1. **Unclaimed Contacts Dashboard** (`/pages/contacts.php`)
   - Filter for unclaimed contacts
   - Bulk claim functionality
   - Claim status indicators

2. **Claim Management Interface**
   - View claimed contacts
   - Release/transfer claims
   - Claim history

3. **Contact Status Indicators**
   - Visual indicators for contact status
   - Claim ownership badges
   - Quick claim/release actions

## 🔄 Implementation Phases

### Phase 1: Database & API Foundation
1. Create new database tables
2. Implement activity feed API endpoints
3. Implement contact claiming API endpoints
4. Update existing contact creation to set `contacted_status = 'not_contacted'`
5. Write comprehensive API tests

### Phase 2: Frontend Integration
1. Update contact view page with activity feed
2. Add activity creation functionality
3. Implement contact claiming interface
4. Update contact list with claiming status
5. Add activity feed to contact cards

### Phase 3: Enhanced Features
1. Activity feed filtering and search
2. Bulk operations for claiming
3. Activity templates and quick actions
4. Email notifications for claims
5. Activity reporting and analytics

### Phase 4: Polish & Testing
1. UI/UX improvements
2. Performance optimization
3. Comprehensive testing
4. Documentation updates
5. User training materials

## 🎨 UI/UX Design Guidelines

### Activity Feed Design
- **Timeline Layout**: Chronological vertical timeline
- **Activity Cards**: Compact cards with type icons
- **Quick Actions**: Inline edit/delete for recent activities
- **Rich Text**: Support for formatted memos
- **Attachments**: Future consideration for file uploads

### Contact Claiming Design
- **Status Badges**: Clear visual indicators
- **Claim Buttons**: Prominent call-to-action buttons
- **Ownership Display**: Show who owns each contact
- **Bulk Operations**: Checkbox selection for multiple contacts
- **Quick Filters**: Filter by claim status, source, date

## 🔒 Security Considerations

### Activity Feed Security
- Users can only view activities for contacts they have access to
- Users can only edit/delete their own activities
- Admins can manage all activities
- Activity data is sanitized and validated

### Contact Claiming Security
- Users can only claim unclaimed contacts
- Users can only release/transfer their own claims
- Admins can override any claim
- Claim history is preserved for audit trails

## 📊 Data Migration Strategy

### Existing Data Handling
1. **Contacts Table**: Add `contacted_status` column with default 'not_contacted'
2. **Existing Contacts**: Set status based on existing data:
   - Contacts with notes → 'contacted'
   - Contacts without notes → 'not_contacted'
   - Customers → 'contacted'

### Activity Data Migration
1. **Convert Existing Notes**: Create activity records from existing contact notes
2. **Preserve History**: Maintain original notes while adding activity structure
3. **User Attribution**: Assign activities to system user or contact creator

## 🧪 Testing Strategy

### API Testing
- Unit tests for all new endpoints
- Integration tests for activity workflows
- Security tests for access control
- Performance tests for large datasets

### Frontend Testing
- Component testing for new UI elements
- User workflow testing
- Cross-browser compatibility
- Mobile responsiveness

### End-to-End Testing
- Complete user journeys
- Data integrity verification
- Performance under load
- Error handling scenarios

## 📈 Success Metrics

### Activity Feed Metrics
- Number of activities created per day/week
- User engagement with activity feed
- Time spent viewing contact activities
- Activity completion rates

### Contact Claiming Metrics
- Number of contacts claimed per day
- Time from creation to claim
- Claim-to-deal conversion rates
- User claim distribution

## 🚀 Deployment Checklist

### Pre-Deployment
- [ ] Database migration scripts tested
- [ ] API endpoints tested and documented
- [ ] Frontend components tested
- [ ] Security review completed
- [ ] Performance testing completed

### Deployment
- [ ] Database schema updated
- [ ] API deployed and tested
- [ ] Frontend deployed and tested
- [ ] Monitoring and logging configured
- [ ] User training materials ready

### Post-Deployment
- [ ] Monitor system performance
- [ ] Gather user feedback
- [ ] Track success metrics
- [ ] Plan iterative improvements

## 📝 Documentation Updates

### API Documentation
- Update OpenAPI specification
- Add new endpoint examples
- Document new data models
- Provide integration examples

### User Documentation
- Activity feed user guide
- Contact claiming workflow
- Best practices for activity tracking
- Troubleshooting guide

### Developer Documentation
- Database schema documentation
- API integration guide
- Frontend component documentation
- Testing guide

---

**Document Version**: 1.0  
**Created**: January 2025  
**Last Updated**: January 2025  
**Status**: Future Implementation (v2.1.0+)  
**Current Focus**: v2.0.0 - First Boot Configuration & MCP Compatibility 