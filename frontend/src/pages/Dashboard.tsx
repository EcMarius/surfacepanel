import React from 'react';
import {
  Box,
  Container,
  Typography,
  Grid,
  Paper,
  Card,
  CardContent,
} from '@mui/material';
import { useSelector } from 'react-redux';
import { RootState } from '../store';

const Dashboard: React.FC = () => {
  const user = useSelector((state: RootState) => state.auth.user);

  return (
    <Container maxWidth="lg" sx={{ mt: 4, mb: 4 }}>
      <Typography variant="h4" gutterBottom>
        Welcome to SurfacePanel
      </Typography>
      <Typography variant="body1" color="text.secondary" gutterBottom>
        {user?.email} | Role: {user?.role}
      </Typography>

      <Grid container spacing={3} sx={{ mt: 2 }}>
        <Grid item xs={12} md={3}>
          <Card>
            <CardContent>
              <Typography color="text.secondary" gutterBottom>
                Domains
              </Typography>
              <Typography variant="h5">0</Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} md={3}>
          <Card>
            <CardContent>
              <Typography color="text.secondary" gutterBottom>
                Databases
              </Typography>
              <Typography variant="h5">0</Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} md={3}>
          <Card>
            <CardContent>
              <Typography color="text.secondary" gutterBottom>
                Email Accounts
              </Typography>
              <Typography variant="h5">0</Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} md={3}>
          <Card>
            <CardContent>
              <Typography color="text.secondary" gutterBottom>
                Disk Usage
              </Typography>
              <Typography variant="h5">0 GB</Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <Paper sx={{ p: 3, mt: 3 }}>
        <Typography variant="h6" gutterBottom>
          Quick Actions
        </Typography>
        <Typography variant="body2" color="text.secondary">
          Your control panel features will appear here. The platform includes:
        </Typography>
        <Box component="ul" sx={{ mt: 2 }}>
          <li>Domain Management</li>
          <li>DNS Configuration</li>
          <li>Database Management (MySQL/PostgreSQL)</li>
          <li>Email Accounts & Forwarders</li>
          <li>SSL/TLS Certificates (Let's Encrypt)</li>
          <li>File Manager</li>
          <li>FTP/SFTP Accounts</li>
          <li>Cron Jobs</li>
          <li>Backups & Restore</li>
          <li>Firewall & Security</li>
          <li>Resource Monitoring</li>
          <li>Web Server Configuration</li>
        </Box>
      </Paper>
    </Container>
  );
};

export default Dashboard;
